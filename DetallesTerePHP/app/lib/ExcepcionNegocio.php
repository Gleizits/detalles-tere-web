<?php

/**
 * Excepción de negocio: mensajes claros para el usuario
 * (stock insuficiente, cotización ya cobrada, monto inválido...).
 *
 * Equivale a los IllegalStateException / IllegalArgumentException
 * que lanzaban los DAO y controladores en la versión Java.
 */
class ExcepcionNegocio extends RuntimeException
{
}
