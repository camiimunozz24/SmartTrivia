<?php

namespace app\validator;
class FotoPerfilValida implements Regla
{
    // Revisa la foto de perfil: que se haya subido, no más de 2 MB, el formato real
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    public function validar(array $datos)
    {
        $foto = $datos['foto'] ?? null;

        if (!$foto || $foto['error'] === UPLOAD_ERR_NO_FILE) {
            return "Subí una foto de perfil.";
        }

        if ($foto['error'] === UPLOAD_ERR_INI_SIZE || $foto['error'] === UPLOAD_ERR_FORM_SIZE
            || $foto['size'] > self::MAX_BYTES) {
            return "La foto no puede pesar más de 2 MB.";
        }

        if ($foto['error'] !== UPLOAD_ERR_OK) {
            return "No se pudo subir la foto. Probá con otra.";
        }

        // Se revisa el contenido real del archivo, no solo la extensión
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($foto['tmp_name']);
        if (!in_array($mime, self::TIPOS, true)) {
            return "La foto debe ser JPG, PNG o WEBP.";
        }

        return null;
    }
}