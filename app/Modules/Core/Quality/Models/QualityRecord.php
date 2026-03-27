<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Quality\Models;

use App\Modules\Core\Quality\Models\Concerns\HasQualityEvents;
use App\Modules\Core\Quality\Models\Concerns\HasQualityEvidence;
use Illuminate\Database\Eloquent\Model;

abstract class QualityRecord extends Model
{
    use HasQualityEvents;
    use HasQualityEvidence;
}
