<?php

namespace TREngine\Engine\Core;

use TREngine\Engine\Fail\FailEngine;

/**
 * Lanceur d'exception personnalisable.
 *
 * @author Sébastien Villemain
 */
trait CoreTraitException
{

    /**
     * Lance une exception personnalisable.
     *
     * @param string $message
     * @param string $failCode
     * @param array $failArgs
     * @throws FailEngine
     */
    protected function throwException(string $message,
                                      string $failCode = "",
                                      array $failArgs = array()): void
    {
        throw new FailEngine($message,
                             $failCode,
                             $failArgs);
    }
}