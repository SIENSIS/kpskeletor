<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use Modules\Devtools\Recipes\DocPage;
use Modules\Devtools\Recipes\RecipeBook;

/**
 * The CRUD recipes page and its catalogue: every card renders with its texts, its source file
 * exists and its doc section exists; the docs viewer refuses unknown names.
 *
 * @internal
 */
final class RecipesTest extends DevtoolsTestCase
{
    public function testEveryRecipeCitesARealFileAndDocSection(): void
    {
        $recipes = (new RecipeBook())->all();
        $docs    = new DocPage();
        $this->assertGreaterThanOrEqual(16, count($recipes));

        foreach ($recipes as $recipe) {
            $this->assertFileExists(ROOTPATH . $recipe['source'], $recipe['id']);
            $ids = array_column($docs->sections($recipe['doc']) ?? [], 'id');
            $this->assertContains($recipe['anchor'], $ids, "{$recipe['id']}: docs/{$recipe['doc']}.md#{$recipe['anchor']}");

            foreach (['ca', 'es', 'en'] as $locale) {
                foreach (['title', 'what', 'when'] as $text) {
                    $key = "Devtools.recipes.{$recipe['id']}.{$text}";
                    $this->assertNotSame($key, lang($key, [], $locale), "{$key} ({$locale})");
                }
            }
        }

        $this->assertSame(count($recipes), count(array_unique(array_column($recipes, 'id'))));
    }

    public function testTheRecipesPageRendersForTheSuperadmin(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $response = $this->send('GET', backend_path('devtools/recipes'));
        $response->assertOK();
        $response->assertSee(lang('Devtools.banner'));

        foreach ((new RecipeBook())->all() as $recipe) {
            $response->assertSee('data-recipe="' . $recipe['id'] . '"');
            $response->assertSee(esc(lang('Devtools.recipes.' . $recipe['id'] . '.title')));
            $this->assertStringContainsString(esc(backend_url('devtools/docs/' . $recipe['doc']) . '#' . $recipe['anchor'], 'attr'), (string) $response->response()->getBody());
        }

        // The code is shown escaped, never as markup.
        $response->assertSee('$crud-&gt;hideFields');
        $response->assertDontSee('<?php');
    }

    public function testTheRecipesPageIsRefusedToOthers(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginWith('devtools.write');
        $this->fromLocalhost();

        $this->send('GET', backend_path('devtools/recipes'))->assertStatus(403);
    }

    public function testTheDocsViewerShowsOnlyPagesOfDocs(): void
    {
        $this->withDevtoolsRoutes();
        $this->loginSuperadmin();
        $this->fromLocalhost();

        $page = $this->send('GET', backend_path('devtools/docs/cruds'));
        $page->assertOK();
        $page->assertSee('id="a-crud-in-a-module"');

        foreach (['missing-page', '%2E%2E', 'CRUDS', 'cruds.md', '.env'] as $name) {
            $this->assertNotFound('GET', backend_path('devtools/docs/' . rawurlencode($name)));
        }

        $this->assertNull((new DocPage())->sections('../README'));
    }

    public function testHeadingSlugsFollowGitHub(): void
    {
        $this->assertSame('a-crud-in-a-module', DocPage::slug('A CRUD in a module'));
        $this->assertSame('configuration-appconfigkpcrudyphp', DocPage::slug('Configuration (`app/Config/Kpcrudy.php`)'));
        $this->assertSame('permissions-d-20-01', DocPage::slug('Permissions (D-20-01)'));
    }
}
