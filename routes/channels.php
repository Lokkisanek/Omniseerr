<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('downloads', function () {
    return true;
});
