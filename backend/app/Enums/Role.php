<?php

namespace App\Enums;

enum Role: string
{
    case Member = 'member';
    case Staff = 'staff';
    case Owner = 'owner';
}
