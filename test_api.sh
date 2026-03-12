#!/bin/bash
# Script de teste das rotas da API
BASE_URL="http://localhost:8000/api"
HEADER='-H "Content-Type: application/json" -H "Accept: application/json"'

echo "============================================"
echo "  TESTE DAS ROTAS - BeTalentech Payment API"
echo "============================================"
echo ""

# 1. Login
echo ">>> POST /api/login (admin)"
RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@betalent.tech","password":"password"}')
echo "$RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$RESPONSE"
TOKEN=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])" 2>/dev/null)
echo ""
echo "TOKEN: $TOKEN"
echo ""

# 2. Listar Produtos (autenticado)
echo ">>> GET /api/products"
curl -s "$BASE_URL/products" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 3. Criar Produto
echo ">>> POST /api/products"
curl -s -X POST "$BASE_URL/products" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"name":"Product D","amount":7500}' | python3 -m json.tool
echo ""

# 4. Listar Gateways
echo ">>> GET /api/gateways"
curl -s "$BASE_URL/gateways" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 5. Listar Usuários
echo ">>> GET /api/users"
curl -s "$BASE_URL/users" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 6. Realizar Pagamento (rota pública)
echo ">>> POST /api/payments"
PAY_RESPONSE=$(curl -s -X POST "$BASE_URL/payments" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"João Silva","email":"joao@email.com","cardNumber":"5569000000006063","cvv":"010","products":[{"product_id":1,"quantity":2},{"product_id":2,"quantity":1}]}')
echo "$PAY_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$PAY_RESPONSE"
TRANSACTION_ID=$(echo "$PAY_RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('transaction_id',''))" 2>/dev/null)
echo ""

# 7. Listar Clientes
echo ">>> GET /api/clients"
curl -s "$BASE_URL/clients" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 8. Listar Transações
echo ">>> GET /api/transactions"
curl -s "$BASE_URL/transactions" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 9. Detalhe de Transação
if [ -n "$TRANSACTION_ID" ]; then
  echo ">>> GET /api/transactions/$TRANSACTION_ID"
  curl -s "$BASE_URL/transactions/$TRANSACTION_ID" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
  echo ""
fi

# 10. Detalhe do Cliente (id=1)
echo ">>> GET /api/clients/1"
curl -s "$BASE_URL/clients/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
echo ""

# 11. Reembolso
if [ -n "$TRANSACTION_ID" ]; then
  echo ">>> POST /api/transactions/$TRANSACTION_ID/refund"
  curl -s -X POST "$BASE_URL/transactions/$TRANSACTION_ID/refund" \
    -H "Accept: application/json" \
    -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
  echo ""
fi

# 12. Teste de Role (login como user normal)
echo ">>> POST /api/login (user normal)"
USER_RESP=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"test@example.com","password":"password"}')
echo "$USER_RESP" | python3 -m json.tool 2>/dev/null || echo "$USER_RESP"
USER_TOKEN=$(echo "$USER_RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])" 2>/dev/null)
echo ""

echo ">>> GET /api/users (como role=user -> deve retornar 403)"
curl -s "$BASE_URL/users" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" | python3 -m json.tool
echo ""

echo ">>> GET /api/gateways (como role=user -> deve retornar 403)"
curl -s "$BASE_URL/gateways" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $USER_TOKEN" | python3 -m json.tool
echo ""

echo "============================================"
echo "  TESTES CONCLUÍDOS"
echo "============================================"
