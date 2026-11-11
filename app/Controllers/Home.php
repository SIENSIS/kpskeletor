<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Frontend\HomePage;

class Home extends BaseController
{
    /**
     * The public home: a module's answer to the `homePage` event (e.g. the page chosen in
     * Settings › Pages), else the theme's default home.
     */
    public function index(): ResponseInterface|string
    {
        $home = new HomePage();
        Events::trigger(HomePage::EVENT, $home);

        if ($home->answered()) {
            return $home->content() ?? '';
        }

        return theme_view('App\Views\home', [
            'title' => lang('Core.home.title'),
            'meta'  => ['description' => lang('Core.home.text'), 'og' => true],
        ]);
    }
}
