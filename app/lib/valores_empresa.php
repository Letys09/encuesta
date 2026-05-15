<?php

if(!function_exists('getValoresEmpresaMap')) {
    function getValoresEmpresaMap() {
        return [
            'honestidad' => 'Honestidad',
            'responsabilidad' => 'Responsabilidad',
            'respeto' => 'Respeto',
            'integridad' => 'Integridad',
            'compromiso' => 'Compromiso',
            'trabajo-en-equipo' => 'Trabajo en equipo',
            'empatia' => 'Empatia',
            'disciplina' => 'Disciplina',
            'lealtad' => 'Lealtad',
            'innovacion' => 'Innovacion',
            'excelencia' => 'Excelencia',
            'transparencia' => 'Transparencia',
            'solidaridad' => 'Solidaridad',
            'servicio' => 'Servicio'
        ];
    }
}

if(!function_exists('getValoresEmpresaList')) {
    function getValoresEmpresaList() {
        $values = [];
        foreach(getValoresEmpresaMap() as $slug => $nombre) {
            $values[] = [
                'slug' => $slug,
                'nombre' => $nombre
            ];
        }

        return $values;
    }
}
