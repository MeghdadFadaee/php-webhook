<?php

class Storage
{
    public static function link(): bool
    {
        if (realpath(PUBLIC_PATH.'/storage')) {
            return true;
        }

        return @symlink(STORAGE_PATH.'/app/public', PUBLIC_PATH.'/storage');
    }
}