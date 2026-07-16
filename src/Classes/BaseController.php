<?php

namespace Dpsoft\Payments\Classes;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dpsoft\Payments\Traits\SetVariables;
use Dpsoft\Payments\Traits\SetRequiredFields;

class BaseController 
{
	use SetVariables,SetRequiredFields;
}
