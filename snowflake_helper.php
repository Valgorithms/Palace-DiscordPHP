<?php
//uses discord snowflake as a parameter
function snowflake_timestamp($snowflake){
	if(\PHP_INT_SIZE === 4){ //x86
		$binary = \str_pad(\base_convert($snowflake, 10, 2), 64, 0, \STR_PAD_LEFT);
		$time = \base_convert(\substr($binary, 0, 42), 2, 10);
		$timestamp = (float) ((((int) \substr($time, 0, -3)) + 1420070400).'.'.\substr($time, -3));
	}
	else{ //x64
		$snowflake = (int) $snowflake;		
		$time = (string) ($snowflake >> 22);
		$timestamp = (float) ((((int) \substr($time, 0, -3)) + 1420070400).'.'.\substr($time, -3));
	}
	if ($this->timestamp <1420070400) {
		return null;
	}
	return $timestamp;
}
?>