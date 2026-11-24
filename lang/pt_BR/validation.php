<?php

return [
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'email' => 'Informe um e-mail válido.',
    'unique' => 'Este :attribute já está cadastrado.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'min' => ['string' => 'O campo :attribute deve ter pelo menos :min caracteres.'],
    'max' => ['string' => 'O campo :attribute deve ter no máximo :max caracteres.'],
    'attributes' => ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'token' => 'token de recuperação'],
];
