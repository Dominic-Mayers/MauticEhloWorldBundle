<?php

namespace MauticPlugin\MauticHelloWorldBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Loader\ParameterLoader;
use Symfony\Component\HttpFoundation\Response;

class TestController extends CommonController
{
    public function testHelloWorld()
    {
        $parameters = (new ParameterLoader())->getParameterBag();
        return new Response('Test parameters->get: '.$parameters->get('hello_world_test'));
    }
}
