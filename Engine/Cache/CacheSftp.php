<?php

namespace TREngine\Engine\Cache;

/**
 * Gestionnaire de fichier via FTP sécurisée.
 *
 * @author Sébastien Villemain
 */
class CacheSftp extends CacheModel
{

    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    protected function canUse(): bool
    {
        // TODO classe a coder..
        return false;
    }
}