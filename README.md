# BeTalenTech Payment API

API RESTful para **processamento de pagamentos com múltiplos gateways**, desenvolvida com **Laravel 12**.
A aplicação implementa um **sistema de fallback automático entre gateways**, autenticação via **Laravel Sanctum**, controle de acesso por **roles** e arquitetura preparada para **extensão de novos gateways de pagamento**.

---

## Índice

- [Requisitos](#requisitos)
- [Instalação e Execução](#instalação-e-execução)
  - [Com Docker (recomendado)](#com-docker-recomendado)
  - [Sem Docker](#sem-docker)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Arquitetura](#arquitetura)
- [Autenticação](#autenticação)
- [Roles e Permissões](#roles-e-permissões)
- [Rotas da API](#rotas-da-api)
  - [Autenticação](#autenticação-1)
  - [Pagamentos](#pagamentos)
  - [Gateways](#gateways)
  - [Usuários](#usuários)
  - [Produtos](#produtos)
  - [Clientes](#clientes)
  - [Transações](#transações)
- [Estrutura do Banco de Dados](#estrutura-do-banco-de-dados)
- [Gateways de Pagamento](#gateways-de-pagamento)
- [Dados Iniciais (Seeds)](#dados-iniciais-seeds)
- [Testes](#testes)

---

## Requisitos

- **PHP** >= 8.2
- **Composer** >= 2.x
- **MySQL** >= 8.0
- **Docker** e **Docker Compose** (recomendado)

Ou, alternativamente sem Docker:

- PHP 8.2 com extensões: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- MySQL 8.0 rodando localmente
- Composer 2.x

---
## Sobre o Desafio

Este projeto foi desenvolvido como parte de um desafio técnico da **BeTalentech**.

A implementação contempla **todos os requisitos do Nível 2**, incluindo:

- API RESTful para processamento de pagamentos
- Integração com múltiplos gateways
- Sistema de **fallback automático entre gateways**
- Controle de acesso por **roles**
- Autenticação baseada em **tokens (Laravel Sanctum)**

Além disso, foram implementados **incrementos inspirados no Nível 3**, visando melhorar a arquitetura e a organização do sistema, como:

- Estrutura baseada em **interfaces para gateways de pagamento**
- **Service Layer** para orquestração do fluxo de pagamentos
- Arquitetura preparada para **adição de novos gateways**
- Sistema de **prioridade dinâmica de gateways**
- Ambiente completo via **Docker**
- Script automatizado de **testes da API**

---
## Instalação e Execução

### Com Docker (recomendado)

1. Clone o repositório:

```bash
git clone https://github.com/DiegoGS1002/betalentech-payment-api.git
cd betalentech-payment-api
```
2. Configure o arquivo `.env`:

```bash
cp .env.example .env
```

3. Suba os containers:

```bash
docker-compose up -d --build
```

Isso irá iniciar **3 serviços**:

| Serviço          | Porta  | Descrição                        |
| ---------------- | ------ | -------------------------------- |
| **app**          | `8000` | Aplicação Laravel (PHP + Apache) |
| **mysql**        | `3307` | Banco de dados MySQL 8           |
| **gateway-mock** | `3001`, `3002` | Mock dos gateways de pagamento |

O entrypoint do container executa automaticamente:
- `composer install`
- Geração da `APP_KEY`
- Publicação das migrations do Sanctum
- `php artisan migrate --force`
- `php artisan db:seed --force`

4. A API estará disponível em:

```
http://localhost:8000/api
```

### Sem Docker

1. Clone o repositório e instale as dependências:

```bash
git clone https://github.com/DiegoGS1002/betalentech-payment-api.git
cd betalentech-payment-api
composer install
```

2. Configure o arquivo `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure a conexão do banco de dados no `.env` e execute as migrations:

```bash
php artisan migrate
php artisan db:seed
```

4. Inicie o servidor:

```bash
php artisan serve
```

---

## Variáveis de Ambiente

| Variável              | Descrição                           | Valor Padrão                              |
| --------------------- | ----------------------------------- | ----------------------------------------- |
| `DB_HOST`             | Host do banco de dados              | `mysql`                                   |
| `DB_DATABASE`         | Nome do banco                       | `betalentech`                             |
| `DB_USERNAME`         | Usuário do banco                    | `laravel`                                 |
| `DB_PASSWORD`         | Senha do banco                      | `laravel`                                 |
| `GATEWAY1_URL`        | URL do Gateway 1                    | `http://gateway-mock:3001`                |
| `GATEWAY1_EMAIL`      | Email de autenticação do Gateway 1  | `dev@betalent.tech`                       |
| `GATEWAY1_TOKEN`      | Token de autenticação do Gateway 1  | `FEC9BB078BF338F464F96B48089EB498`        |
| `GATEWAY2_URL`        | URL do Gateway 2                    | `http://gateway-mock:3002`                |
| `GATEWAY2_AUTH_TOKEN`  | Token de autenticação do Gateway 2 | `tk_f2198cc671b5289fa856`                 |
| `GATEWAY2_AUTH_SECRET` | Secret do Gateway 2                | `3d15e8ed6131446ea7e3456728b1211f`        |

---

## Tecnologias Utilizadas

- **PHP 8.2**
- **Laravel 12**
- **MySQL 8**
- **Docker / Docker Compose**
- **Laravel Sanctum** (autenticação)
- **Pest** (testes)
- **cURL / Bash** para testes manuais da API

---

## Arquitetura

```
app/
├── Gateways/
│   ├── Contracts/
│   │   └── PaymentGatewayInterface.php   # Contrato dos gateways
│   ├── Gateway1Service.php               # Implementação Gateway 1
│   └── Gateway2Service.php               # Implementação Gateway 2
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php            # Login / Logout
│   │   ├── ClientController.php          # CRUD de clientes
│   │   ├── GatewayController.php         # Gerenciamento de gateways
│   │   ├── PaymentController.php         # Processamento de pagamentos
│   │   ├── ProductController.php         # CRUD de produtos
│   │   ├── TransactionController.php     # Consulta e reembolso de transações
│   │   └── UserController.php            # CRUD de usuários
│   └── Middleware/
│       └── CheckRole.php                 # Middleware de controle de acesso por role
├── Models/
│   ├── Client.php
│   ├── Gateway.php
│   ├── Product.php
│   ├── Transaction.php
│   ├── TransactionProduct.php
│   └── User.php
└── Services/
    └── PaymentService.php                # Orquestração de pagamentos com fallback
```

O fluxo de pagamento segue o padrão:

1. `PaymentController` recebe a requisição e valida os dados
2. `PaymentService` busca os gateways ativos ordenados por prioridade
3. Tenta processar no gateway de maior prioridade
4. Se falhar, tenta automaticamente no próximo gateway (**fallback**)
5. Cria o registro de `Transaction` e `TransactionProduct` ao obter sucesso

---

## Autenticação

A API utiliza **Laravel Sanctum** para autenticação via tokens Bearer.

**Como obter um token:**

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@betalent.tech", "password": "password"}'
```

**Resposta:**

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@betalent.tech",
    "role": "admin"
  },
  "token": "1|abc123..."
}
```

**Usar o token nas requisições:**

```bash
curl -H "Authorization: Bearer 1|abc123..." http://localhost:8000/api/products
```

---

## Roles e Permissões

O sistema possui 4 roles com permissões hierárquicas:

| Role        | Descrição                                                  |
| ----------- | ---------------------------------------------------------- |
| `admin`     | Acesso total a todos os recursos                           |
| `manager`   | Gerenciamento de usuários e produtos                       |
| `finance`   | Gerenciamento de produtos e reembolso de transações        |
| `user`      | Consulta de clientes e transações                          |

> O `admin` sempre tem acesso, independentemente da restrição de role na rota.

---

## Rotas da API

Base URL: `http://localhost:8000/api`

### Autenticação

| Método | Rota       | Descrição          | Autenticação |
| ------ | ---------- | ------------------ | ------------ |
| POST   | `/login`   | Login (gera token) | Não          |
| POST   | `/logout`  | Logout             | Sim          |

#### POST `/login`

**Body:**

```json
{
  "email": "admin@betalent.tech",
  "password": "password"
}
```

**Resposta (201):**

```json
{
  "user": { "id": 1, "name": "Admin", "email": "admin@betalent.tech", "role": "admin" },
  "token": "1|abc123..."
}
```

---

### Pagamentos

| Método | Rota        | Descrição             | Autenticação |
| ------ | ----------- | --------------------- | ------------ |
| POST   | `/payments` | Processar um pagamento | Não          |

#### POST `/payments`

**Body:**

```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "cardNumber": "1234567890123456",
  "cvv": "123",
  "products": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

| Campo              | Tipo    | Obrigatório | Validação                    |
| ------------------ | ------- | ----------- | ---------------------------- |
| `name`             | string  | Sim         | máx. 255 caracteres          |
| `email`            | string  | Sim         | email válido, máx. 255       |
| `cardNumber`       | string  | Sim         | exatamente 16 caracteres     |
| `cvv`              | string  | Sim         | 3 a 4 caracteres             |
| `products`         | array   | Sim         | mínimo 1 item                |
| `products.*.product_id` | integer | Sim   | deve existir na tabela products |
| `products.*.quantity`   | integer | Sim   | mínimo 1                    |

**Resposta de sucesso (201):**

```json
{
  "success": true,
  "transaction_id": 1,
  "external_id": "ext_123",
  "gateway": "gateway1",
  "amount": 4500,
  "status": "approved"
}
```

**Resposta de falha (422):**

```json
{
  "success": false,
  "error": "Payment failed on all gateways",
  "last_gateway_error": { ... }
}
```

> **Nota:** O campo `amount` é armazenado em **centavos** (integer). Exemplo: R$ 45,00 = `4500`.

---

### Gateways

> Requer role: **admin**

| Método | Rota                            | Descrição              |
| ------ | ------------------------------- | ---------------------- |
| GET    | `/gateways`                     | Listar todos os gateways |
| PATCH  | `/gateways/{id}/activate`       | Ativar um gateway      |
| PATCH  | `/gateways/{id}/deactivate`     | Desativar um gateway   |
| PATCH  | `/gateways/{id}/priority`       | Alterar prioridade     |

#### PATCH `/gateways/{id}/priority`

**Body:**

```json
{
  "priority": 1
}
```

**Resposta (200):**

```json
{
  "message": "Priority updated",
  "gateway": { "id": 1, "name": "gateway1", "is_active": true, "priority": 1 }
}
```

---

### Usuários

> Requer role: **admin** ou **manager**

| Método | Rota           | Descrição            |
| ------ | -------------- | -------------------- |
| GET    | `/users`       | Listar todos         |
| POST   | `/users`       | Criar novo usuário   |
| GET    | `/users/{id}`  | Detalhar um usuário  |
| PUT    | `/users/{id}`  | Atualizar um usuário |
| DELETE | `/users/{id}`  | Excluir um usuário   |

#### POST `/users`

**Body:**

```json
{
  "name": "Novo Usuário",
  "email": "novo@email.com",
  "password": "senha123",
  "role": "finance"
}
```

| Campo      | Tipo   | Obrigatório | Validação                            |
| ---------- | ------ | ----------- | ------------------------------------ |
| `name`     | string | Sim         | máx. 255 caracteres                  |
| `email`    | string | Sim         | email válido, único na tabela users  |
| `password` | string | Sim         | mínimo 6 caracteres                  |
| `role`     | string | Sim         | `admin`, `manager`, `finance` ou `user` |

---

### Produtos

> Requer role: **admin**, **manager** ou **finance**

| Método | Rota              | Descrição             |
| ------ | ----------------- | --------------------- |
| GET    | `/products`       | Listar todos          |
| POST   | `/products`       | Criar novo produto    |
| GET    | `/products/{id}`  | Detalhar um produto   |
| PUT    | `/products/{id}`  | Atualizar um produto  |
| DELETE | `/products/{id}`  | Excluir um produto    |

#### POST `/products`

**Body:**

```json
{
  "name": "Produto D",
  "amount": 3000
}
```

| Campo    | Tipo    | Obrigatório | Validação           |
| -------- | ------- | ----------- | ------------------- |
| `name`   | string  | Sim         | máx. 255 caracteres |
| `amount` | integer | Sim         | mínimo 1 (centavos) |

---

### Clientes

> Requer autenticação (qualquer role)

| Método | Rota             | Descrição                               |
| ------ | ---------------- | --------------------------------------- |
| GET    | `/clients`       | Listar todos os clientes                |
| GET    | `/clients/{id}`  | Detalhar cliente com transações e produtos |

Os clientes são criados automaticamente durante o processamento de pagamentos.

---

### Transações

> Requer autenticação (qualquer role para consulta)

| Método | Rota                            | Descrição                  | Role mínima       |
| ------ | ------------------------------- | -------------------------- | ----------------- |
| GET    | `/transactions`                 | Listar todas as transações | Qualquer          |
| GET    | `/transactions/{id}`            | Detalhar uma transação     | Qualquer          |
| POST   | `/transactions/{id}/refund`     | Reembolsar uma transação   | `admin` ou `finance` |

#### POST `/transactions/{id}/refund`

**Resposta de sucesso (200):**

```json
{
  "success": true,
  "message": "Refund processed successfully",
  "transaction": { ... }
}
```

**Resposta de erro (422):**

```json
{
  "error": "Transaction already refunded"
}
```

---

## Estrutura do Banco de Dados

### Tabela `users`

| Coluna              | Tipo      | Descrição                    |
| ------------------- | --------- | ---------------------------- |
| `id`                | bigint PK | Identificador                |
| `name`              | string    | Nome do usuário              |
| `email`             | string    | Email (único)                |
| `password`          | string    | Senha (hash)                 |
| `role`              | string    | Role (`admin`, `manager`, `finance`, `user`) |
| `email_verified_at` | timestamp | Data de verificação do email |
| `created_at`        | timestamp | Data de criação              |
| `updated_at`        | timestamp | Data de atualização          |

### Tabela `gateways`

| Coluna       | Tipo       | Descrição                 |
| ------------ | ---------- | ------------------------- |
| `id`         | bigint PK  | Identificador             |
| `name`       | string     | Nome do gateway           |
| `is_active`  | boolean    | Se está ativo (default: true) |
| `priority`   | integer    | Prioridade de uso (menor = maior) |
| `created_at` | timestamp  | Data de criação           |
| `updated_at` | timestamp  | Data de atualização       |

### Tabela `clients`

| Coluna       | Tipo      | Descrição       |
| ------------ | --------- | --------------- |
| `id`         | bigint PK | Identificador   |
| `name`       | string    | Nome do cliente |
| `email`      | string    | Email           |
| `created_at` | timestamp | Data de criação |
| `updated_at` | timestamp | Data de atualização |

### Tabela `products`

| Coluna       | Tipo      | Descrição                    |
| ------------ | --------- | ---------------------------- |
| `id`         | bigint PK | Identificador                |
| `name`       | string    | Nome do produto              |
| `amount`     | integer   | Valor em centavos            |
| `created_at` | timestamp | Data de criação              |
| `updated_at` | timestamp | Data de atualização          |

### Tabela `transactions`

| Coluna              | Tipo      | Descrição                         |
| ------------------- | --------- | --------------------------------- |
| `id`                | bigint PK | Identificador                     |
| `client_id`         | bigint FK | Referência ao cliente             |
| `gateway_id`        | bigint FK | Referência ao gateway utilizado   |
| `external_id`       | string    | ID externo retornado pelo gateway |
| `status`            | string    | Status (`approved`, `refunded`)   |
| `amount`            | integer   | Valor total em centavos           |
| `card_last_numbers` | string(4) | Últimos 4 dígitos do cartão      |
| `created_at`        | timestamp | Data de criação                   |
| `updated_at`        | timestamp | Data de atualização               |

### Tabela `transaction_products`

| Coluna           | Tipo      | Descrição                   |
| ---------------- | --------- | --------------------------- |
| `id`             | bigint PK | Identificador               |
| `transaction_id` | bigint FK | Referência à transação      |
| `product_id`     | bigint FK | Referência ao produto       |
| `quantity`        | integer   | Quantidade                  |
| `created_at`     | timestamp | Data de criação             |
| `updated_at`     | timestamp | Data de atualização         |

---

## Gateways de Pagamento

A API suporta múltiplos gateways com sistema de **fallback automático**. Se o gateway de maior prioridade falhar, o próximo é tentado automaticamente.

### Gateway 1

- **Autenticação:** Login com email + token → recebe Bearer token (cacheado por ~58 min)
- **Processar pagamento:** `POST /transactions` com `amount`, `name`, `email`, `cardNumber`, `cvv`
- **Reembolso:** `POST /transactions/{id}/charge_back`

### Gateway 2

- **Autenticação:** Headers estáticos (`Gateway-Auth-Token` + `Gateway-Auth-Secret`)
- **Processar pagamento:** `POST /transacoes` com `valor`, `nome`, `email`, `numeroCartao`, `cvv`
- **Reembolso:** `POST /transacoes/reembolso` com `id`

### Adicionando novos gateways

1. Crie uma classe implementando `PaymentGatewayInterface` em `app/Gateways/`
2. Registre o novo gateway no array `$gatewayMap` em `PaymentService`
3. Adicione o registro na tabela `gateways` via seeder ou migration

---

## Dados Iniciais (Seeds)

Ao executar `php artisan db:seed`, os seguintes dados são criados:

**Usuários:**

| Email                  | Senha      | Role  |
| ---------------------- | ---------- | ----- |
| `admin@betalent.tech`  | `password` | admin |
| `test@example.com`     | `password` | user  |

**Produtos:**

| Nome      | Valor (centavos) |
| --------- | ---------------- |
| Product A | 1000 (R$ 10,00)  |
| Product B | 2500 (R$ 25,00)  |
| Product C | 5000 (R$ 50,00)  |

**Gateways:**

| Nome     | Prioridade | Ativo |
| -------- | ---------- | ----- |
| gateway1 | 1          | Sim   |
| gateway2 | 2          | Sim   |

---

## Testes

O projeto utiliza **Pest** como framework de testes.

```bash
# Executar todos os testes
php artisan test

# Ou com Pest diretamente
./vendor/bin/pest
```

Há também um script de teste manual da API:

```bash
# Testa todos os endpoints da API (requer bash e curl)
bash test_api.sh
```

O script `test_api.sh` executa sequencialmente:
1. Login como admin e extração do token
2. Listagem e criação de produtos
3. Listagem de gateways
4. Listagem de usuários
5. Processamento de pagamento (endpoint público)
6. Consulta de clientes e transações
7. Reembolso de transação
8. Teste de permissões (login como user normal e tentativa de acesso a rotas restritas)
