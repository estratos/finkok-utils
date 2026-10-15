<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Exception;

/**
 * Marca común de todos los errores que produce el bundle.
 *
 * Permite capturar cualquier fallo del adaptador de utilerías con un solo
 * `catch (FinkokExceptionInterface $e)` sin acoplarse a las clases concretas.
 */
interface FinkokExceptionInterface extends \Throwable
{
}
