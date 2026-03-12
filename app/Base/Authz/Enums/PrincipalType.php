<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Authz\Enums;

enum PrincipalType: string
{
    case HUMAN_USER = 'human_user';
    case AGENT = 'agent';
}
