<?php

return [
    'class' => [
        'creation_failed' => 'Error al crear la clase de impuesto',
        'update_failed' => 'Error al actualizar la clase de impuesto',
        'delete_default' => 'No se puede eliminar la clase de impuesto predeterminada; designa otra como predeterminada primero',
        'in_use' => 'La clase de impuesto sigue asignada a productos y no se puede eliminar',
    ],
    'rate' => [
        'creation_failed' => 'Error al crear la tasa de impuesto',
        'update_failed' => 'Error al actualizar la tasa de impuesto',
    ],
    'configuration' => [
        'invalid_class' => 'Un producto hace referencia a una clase de impuesto que ya no existe; el checkout no puede continuar',
        'no_default' => 'No hay ninguna clase de impuesto predeterminada configurada',
    ],
];
