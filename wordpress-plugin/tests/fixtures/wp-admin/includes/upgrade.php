<?php

function dbDelta( string $query ): void {
	$GLOBALS['created_table_sql'] = $query;
}
