#!/bin/bash

# Script para executar testes no container Docker
# Uso: ./run_tests.sh [filtro]
# Exemplo: ./run_tests.sh AuthTest

if [ -z "$1" ]; then
    echo "Executando todos os testes..."
    docker compose exec app php artisan test
else
    echo "Executando testes com filtro: $1"
    docker compose exec app php artisan test --filter="$1"
fi

