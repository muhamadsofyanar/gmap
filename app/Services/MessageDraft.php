<?php
namespace App\Services;
final class MessageDraft
{
    public static function text(string $message): string
    {
        $message=trim($message);
        if($message==='' || mb_strlen($message)>4000 || str_contains($message,"\0")) throw new \InvalidArgumentException('Tulis pesan antara 1 dan 4.000 karakter.');
        return $message;
    }
}
