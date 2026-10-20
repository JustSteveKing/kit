<?php

declare(strict_types=1);

/*
 * Spanish details for the problems api-toolbox raises itself. English comes from the package, and
 * any line missing here falls back to it. Keys follow vendor/juststeveking/api-toolbox/resources/lang/en/problems.php.
 */
return [
    'conditional_requests' => [
        'precondition_failed' => 'El recurso cambio desde la ultima vez que lo obtuviste. Vuelve a obtenerlo y reintenta con su ETag actual.',
        'precondition_required' => 'Esta solicitud debe ser condicional. Obten el recurso y reintenta con su ETag en If-Match.',
    ],

    'pagination' => [
        'invalid_cursor' => 'No se pudo decodificar el parametro :parameter.',
    ],

    'idempotency' => [
        'conflict' => 'Esta Idempotency-Key ya se uso con un payload diferente. Reenvia el payload original o usa una clave nueva para una solicitud nueva.',
        'in_flight' => 'Una solicitud con esta Idempotency-Key todavia se esta procesando. Reintenta la misma solicitud cuando termine.',
        'missing' => 'Esta operacion requiere una cabecera Idempotency-Key.',
        'malformed' => 'La cabecera Idempotency-Key debe ser una cadena de 1 a 255 caracteres ASCII visibles, como "8e03978e-40d5-43e8-bc93-6894a57f9324".',
    ],

    'negotiation' => [
        'not_acceptable' => 'Este endpoint solo puede producir :types, que no coincide con la cabecera Accept.',
        'missing_content_type' => 'La solicitud tiene contenido pero no Content-Type. Tipos de contenido admitidos: :types.',
        'unsupported_media_type' => "El Content-Type ':type' no es compatible. Tipos de contenido admitidos: :types.",
    ],

    'async_operations' => [
        'not_found' => 'No se conoce ninguna operacion con este ID.',
    ],

    'merge_patch' => [
        'malformed' => 'No se pudo interpretar el merge patch como JSON: :reason.',
        'missing_content_type' => 'Un PATCH necesita un Content-Type :type.',
        'unsupported_type' => "El formato de patch ':received' no es compatible. Envia :type.",
    ],

    'digest' => [
        'missing' => 'Esta solicitud necesita un campo Content-Digest o Repr-Digest.',
        'not_a_dictionary' => ':field no es un Dictionary de Structured Fields (RFC 9651, seccion 3.2).',
        'unsupported_algorithms' => 'Ninguno de los algoritmos de hash de la solicitud es compatible. Usa sha-256 o sha-512.',
        'invalid_values' => 'Un digest de la solicitud no puede provenir de su algoritmo de hash.',
        'mismatched_values' => 'Un digest de la solicitud no coincide con su contenido. Puede haberse modificado en transito.',
    ],

    'message_signatures' => [
        'malformed' => 'Signature o Signature-Input no es un Dictionary valido: :error',
        'verification_failed' => 'No se pudo verificar la firma de la solicitud: :reason',
        'reasons' => [
            'not_signed' => 'la solicitud no esta firmada.',
            'unknown_key' => 'ninguna firma usa una clave conocida.',
            'algorithm_mismatch' => 'el parametro alg no coincide con la clave.',
            'missing_tag' => 'la firma no lleva la etiqueta requerida.',
            'missing_nonce' => 'la firma no tiene nonce.',
            'not_covered' => 'la firma no cubre :component.',
            'unresolvable_component' => 'no se pudo resolver un componente cubierto.',
            'mismatch' => 'la firma no coincide.',
            'content_digest_mismatch' => 'Content-Digest no coincide con el contenido.',
            'missing_created' => 'la firma no tiene hora de creacion.',
            'created_in_future' => 'la firma se creo en el futuro.',
            'too_old' => 'la firma es demasiado antigua.',
            'expired' => 'la firma ha caducado.',
        ],
    ],
];
