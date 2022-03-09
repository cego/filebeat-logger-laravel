<?php

/**
 * This line eager loads the log facade.
 * While this seems unnecessary, it is very important that this line is NOT removed,
 * and that it continues to be eager loaded.
 *
 * The reason being if the PHP application runs out-of-memory BEFORE any log has been written,
 * then the application has no leftover memory to load the log facade.
 * Meaning it will be impossible to log that the out-of-memory error happened.
 */
use Illuminate\Support\Facades\Log;

