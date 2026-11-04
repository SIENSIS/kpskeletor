<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Registry\ModuleState;

/**
 * Global filter (`moduleenabled`): a route whose controller belongs to a disabled module
 * (`Modules\<Name>\…`) answers 404, as if the module were not installed. Routes stay in the
 * module's `Config/Routes.php`, which never queries the database; the registry is cached.
 */
final class ModuleEnabled implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $controller = service('router')->controllerName();

        if (! is_string($controller)) {
            return null;
        }

        $module = ModuleState::moduleOf($controller);

        if ($module !== null && ! (new ModuleState())->isEnabled($module)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
