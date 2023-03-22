<?php

// src/HuwelijksplannerBundle.php

namespace CommonGateway\HuwelijksplannerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HuwelijksplannerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
