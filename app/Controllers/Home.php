<?php

declare(strict_types=1);

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        return theme_view('App\Views\home', [
            'title' => lang('Core.home.title'),
            'meta'  => ['description' => lang('Core.home.text'), 'og' => true],
        ]);
    }
}
