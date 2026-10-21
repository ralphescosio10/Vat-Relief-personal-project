<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Accountant = 'accountant';
    case Reviewer = 'reviewer';
    case ClientUser = 'client_user';
}
