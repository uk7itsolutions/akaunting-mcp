<?php

use App\Mcp\Servers\AkauntingServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', AkauntingServer::class)
    ->middleware(['validate.akaunting.key']);
