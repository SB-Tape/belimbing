<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\AI\Tools;

/**
 * Thrown when a tool argument fails validation.
 *
 * Caught by AbstractTool::execute() and formatted as an error response.
 * Use this for input validation errors that should be reported to the LLM
 * without a stack trace.
 */
final class ToolArgumentException extends \InvalidArgumentException {}
