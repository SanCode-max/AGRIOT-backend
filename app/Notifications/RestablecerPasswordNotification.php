<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RestablecerPasswordNotification extends Notification
{
    use Queueable;

    public $token;
    public $correo;

    public function __construct($token, $correo)
    {
        $this->token = $token;
        $this->correo = $correo;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // URL de tu formulario en React donde el usuario ingresará la nueva clave
        $urlFrontend = env('FRONTEND_URL', 'https://frontend-agriot.vercel.app') 
            . '/restablecer-password?token=' . $this->token 
            . '&correo=' . urlencode($this->correo);

        return (new MailMessage)
            ->subject('Restablecimiento de Contraseña - AgrIoT')
            ->greeting('Hola, ' . $notifiable->nombre)
            ->line('Has recibido este correo porque solicitaste un restablecimiento de contraseña para tu cuenta de AgrIoT.')
            ->action('Restablecer Contraseña', $urlFrontend)
            ->line('Este enlace caducará en 1 hora.')
            ->line('Si no solicitaste este cambio, no es necesaria ninguna otra acción.');
    }
}