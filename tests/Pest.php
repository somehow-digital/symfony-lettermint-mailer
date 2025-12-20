<?php

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->usePutenv()->load(dirname(__DIR__) . '/.env');
