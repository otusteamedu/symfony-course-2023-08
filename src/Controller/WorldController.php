<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

class WorldController
{
    public function hello(): Response
    {
        return new Response('<html><body><h1><b>Hello,</b> world!</h1></body></html>');
    }
}
