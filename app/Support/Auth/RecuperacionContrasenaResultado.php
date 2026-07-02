<?php

namespace App\Support\Auth;

final class RecuperacionContrasenaResultado
{
    public const ESTADO_ENVIADO = 'enviado';
    public const ESTADO_SIN_EMAIL = 'sin_email';
    public const ESTADO_DNI_NO_ENCONTRADO = 'dni_no_encontrado';
    public const ESTADO_CONTRASENA_NO_ENVIABLE = 'contrasena_no_enviable';
    public const ESTADO_MAIL_NO_CONFIGURADO = 'mail_no_configurado';
    public const ESTADO_ERROR_ENVIO = 'error_envio';
    public const ESTADO_DNI_INVALIDO = 'dni_invalido';
    public const ESTADO_NIVEL_INVALIDO = 'nivel_invalido';
    public const ESTADO_LIMITE_INTENTOS = 'limite_intentos';

    private function __construct(
        public readonly string $estado,
        public readonly ?string $emailDestino = null,
        public readonly ?string $mensajeError = null,
    ) {}

    public static function enviado(string $emailDestino): self
    {
        return new self(self::ESTADO_ENVIADO, $emailDestino);
    }

    public static function sinEmail(): self
    {
        return new self(self::ESTADO_SIN_EMAIL);
    }

    public static function dniNoEncontrado(): self
    {
        return new self(self::ESTADO_DNI_NO_ENCONTRADO);
    }

    public static function contrasenaNoEnviable(): self
    {
        return new self(self::ESTADO_CONTRASENA_NO_ENVIABLE);
    }

    public static function mailNoConfigurado(): self
    {
        return new self(self::ESTADO_MAIL_NO_CONFIGURADO);
    }

    public static function errorEnvio(string $mensajeError): self
    {
        return new self(self::ESTADO_ERROR_ENVIO, mensajeError: $mensajeError);
    }

    public static function dniInvalido(): self
    {
        return new self(self::ESTADO_DNI_INVALIDO);
    }

    public static function nivelInvalido(): self
    {
        return new self(self::ESTADO_NIVEL_INVALIDO);
    }

    public static function limiteIntentos(int $segundos): self
    {
        return new self(
            self::ESTADO_LIMITE_INTENTOS,
            mensajeError: (string) $segundos,
        );
    }

    public function fueEnviado(): bool
    {
        return $this->estado === self::ESTADO_ENVIADO;
    }
}
