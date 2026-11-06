<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Filters\ContentSecurity;
use Modules\Core\Settings\SecurityRules;
use Modules\Core\Settings\SettingCaster;
use Modules\Core\Settings\SettingDefinition;
use Modules\Settings\Config\Module;
use Modules\Settings\Forms\Field;
use Modules\Settings\Forms\FormGenerator;
use Modules\Settings\Services\SettingsEditor;

/**
 * Typed casting, the rules a field gets from its type, the security groups, and the CSP filter
 * ignoring a weakening value stored another way.
 *
 * @internal
 */
final class SettingsFormTest extends CIUnitTestCase
{
    public function testTheModuleGrantsOnlySettingsUpdateAndSecurity(): void
    {
        $operations = \Modules\Core\Permissions\CatalogueBuilder::operations((new Module())->resources['settings']);

        $this->assertSame(['update', 'security'], array_keys($operations));
    }

    public function testPostedTextIsCastToTheSettingType(): void
    {
        $field = static fn (string $type, mixed $default = null): Field => new Field('M-k', 'M.k', $type, 'x', $default, $default);

        $this->assertSame(42, $field('int')->cast('42'));
        $this->assertSame(1.5, $field('float')->cast('1.5'));
        $this->assertTrue($field('bool')->cast('1'));
        $this->assertFalse($field('bool')->cast(null));
        $this->assertSame(['a.example.com', 'b.example.com'], $field('list')->cast(" a.example.com \r\n\r\nb.example.com\na.example.com"));
        $this->assertSame(['admin' => 'required'], $field('json')->cast('{"admin":"required"}'));
        $this->assertSame([], $field('json')->cast(''));
        $this->assertSame('Name', $field('string')->cast('  Name '));
        $this->assertSame(['x', 'y'], SettingCaster::cast('list', '["x","y"]'));
    }

    public function testTypesAddTheirOwnRules(): void
    {
        $int = new Field('M-k', 'M.k', 'int', 'x', 1, 1, 'required|greater_than[0]');
        $this->assertSame(['integer', 'required', 'greater_than[0]'], $int->validationRules());

        $select = new Field('M-k', 'M.k', 'select', 'x', 'a', 'a', '', null, ['a' => 'A', 'b' => 'B']);
        $this->assertSame(['permit_empty', 'in_list[a,b]'], $select->validationRules());

        $result = (new FormGenerator())->validate([$select, new Field('M-c', 'M.c', 'color', 'x', '', '')], ['M-k' => 'z', 'M-c' => 'red;}']);
        $this->assertSame(['M-k', 'M-c'], array_keys($result['errors']));

        $result = (new FormGenerator())->validate([$select], ['M-k' => ['b']]);
        $this->assertSame(['M.k' => ''], $result['values'], 'An array posted for a field is emptied.');
    }

    public function testSecurityGroupsGoToSettingsSecurity(): void
    {
        $definition = static fn (?string $group): SettingDefinition => new SettingDefinition('M', 'k', 'int', 1, 'x', '', null, $group);

        $this->assertTrue(SettingsEditor::isSecurity($definition('security')));
        $this->assertTrue(SettingsEditor::isSecurity($definition('security.csp')));
        $this->assertFalse(SettingsEditor::isSecurity($definition('securityish')));
        $this->assertFalse(SettingsEditor::isSecurity($definition(null)));
    }

    public function testEveryDeclaredRuleExists(): void
    {
        $sets = config('Validation')->ruleSets;
        $this->assertContains(SecurityRules::class, $sets);

        foreach ((new \Modules\Core\Modules\ModuleDiscovery())->discover() as $module) {
            foreach ($module->manifest->settings ?? [] as $key => $definition) {
                foreach (explode('|', (string) ($definition['rules'] ?? '')) as $rule) {
                    $name = explode('[', $rule, 2)[0];

                    if ($name === '' || in_array($name, ['required', 'permit_empty'], true)) {
                        continue;
                    }

                    $found = array_filter($sets, static fn (string $set): bool => method_exists($set, $name));
                    $this->assertNotSame([], $found, "{$module->name}.{$key}: rule {$name}");
                }
            }
        }

        $this->assertSame(8.0, SecurityRules::configValue('Auth.minimumPasswordLength'));
        $this->assertNull(SecurityRules::configValue('Auth.nothing'));
    }

    public function testTheCspFilterIgnoresWeakeningValuesStoredAnotherWay(): void
    {
        $stored = [
            'Core.cspScriptSrc' => ["'unsafe-inline'", 'https://*.example.com', 'https://cdn.example.com'],
            'Core.cspImgSrc'    => ['*', 'data:', 'https://*.images.example.com'],
            'Core.cspFrameSrc'  => "https://video.example.com\nhttp://plain.example.com",
        ];

        $sources = ContentSecurity::settingSources(static fn (string $name): mixed => $stored[$name] ?? null);

        $this->assertSame([
            'script-src' => ['https://cdn.example.com'],
            'img-src'    => ['https://*.images.example.com'],
            'frame-src'  => ['https://video.example.com'],
        ], $sources);
    }
}
