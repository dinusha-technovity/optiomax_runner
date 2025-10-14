<?php

namespace App\View\Components;

use Illuminate\View\Component;

class EmailLayout extends Component
{
    public $appDetailsUrl;

    public function __construct($appDetailsUrl = '#')
    {
        $this->appDetailsUrl = $appDetailsUrl;
    }

    public function render()
    {
        return view('components.email-layout');
    }
}
