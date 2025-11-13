# POST /api/auth/register

Documentação detalhada do endpoint responsável pelo cadastro de usuários e disparo do fluxo de verificação de e-mail.

---

## 📌 Visão Geral

| Item | Descrição |
| --- | --- |
| **Endpoint** | `POST /api/auth/register` |
| **Headers obrigatórios** | `Content-Type: application/json` |
| **Autenticação** | Não requer token |
| **Fila/Jobs** | Sim – `SendVerificationEmailJob` |
| **Status possíveis** | `201`, `409`, `422`, `500` |

O endpoint cria um novo usuário com status `pending_verification`, gera o token de verificação (TTL de 15 minutos, armazenado em cache/Redis) e enfileira o envio do e-mail de confirmação via SendGrid.

---

## 🧾 Exemplo de Requisição

```bash
curl -X POST http://localhost/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Max Mateus",
    "email": "max@example.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "cpf": "123.456.789-09",
    "phone": "(11) 99999-9999",
    "birthdate": "1995-01-15",
    "gender": "M",
    "accept_terms": true,
    "street": "Rua Exemplo",
    "number": "123",
    "complement": "Apto 45",
    "neighborhood": "Centro",
    "city": "São Paulo",
    "state": "SP",
    "zip_code": "01234-567"
  }'
```

---

## ✅ Resposta de Sucesso (201)

```json
{
  "message": "Usuário criado com sucesso. Verifique seu e-mail para ativar a conta."
}
```

---

## 📋 Validações e Regras de Negócio

| Campo | Tipo | Regras |
| --- | --- | --- |
| `name` | string | obrigatório, 3–255 caracteres |
| `email` | string | obrigatório, formato válido, único |
| `password` | string | obrigatório, min. 8, `password_confirmation` deve coincidir |
| `cpf` | string | obrigatório, 11 dígitos, validação algorítmica |
| `phone` | string | obrigatório, 10–15 caracteres (apenas dígitos após normalização) |
| `birthdate` | date | obrigatório, usuário ≥ 18 anos |
| `gender` | enum | obrigatório, `M`, `F` ou `Outro` |
| `accept_terms` | boolean | obrigatório, deve ser `true` |
| `street`, `number`, `neighborhood`, `city`, `state`, `zip_code` | string | obrigatórios (estado com 2 caracteres, CEP 8–10 caracteres) |
| `complement` | string | opcional |

Outras regras aplicadas pelo serviço:

- **Normalização**: e-mail em minúsculo, CPF/telefone/CEP somente dígitos, estado em caixa alta.
- **Status inicial**: `pending_verification`.
- **Conflitos**: e-mail e CPF devem ser únicos. Conflitos são tratados via `UserAlreadyExistsException` → HTTP `409`.
- **CPF inválido**: lança `InvalidCpfException` → HTTP `422`.
- **Persistência**: orquestrada por `RegisterUserService` (Service → Repository → Model).

---

## 🔐 Fluxo de Verificação de E-mail

1. Usuário é criado com status `pending_verification`.
2. `EmailVerificationService` gera token (UUID) com TTL de 15 minutos e salva em cache (prefixo `email_verifications:`).
3. `SendVerificationEmailJob` é enfileirado contendo nome, e-mail e token.
4. Worker (`php artisan queue:work`) executa o job, que chama `SendGridService` para enviar o e-mail com o link `GET /api/auth/verify-email?token=UUID`.
5. Ao confirmar, o endpoint de verificação:
   - valida o token (existência + prazo),
   - atualiza `email_verified_at` e `status = active`,
   - registra o método `email` como verificado em `mfa_methods`,
   - remove o token do cache.

---

## 🧨 Tratamento de Erros

| Status | Quando ocorre | Corpo da resposta |
| --- | --- | --- |
| `409 Conflict` | e-mail ou CPF já cadastrados | `{ "message": "Usuário já cadastrado no sistema.", "errors": { "email": "...", "cpf": "..." } }` |
| `422 Unprocessable Entity` | CPF inválido (algoritmo) ou outra validação do `RegisterRequest` | `{ "message": "O CPF informado não é válido." }` ou objeto com detalhes dos campos |
| `500 Internal Server Error` | falha inesperada ao criar usuário ou disparar o job | `{ "message": "Erro interno do servidor" }` |

> **Importante:** o endpoint não bloqueia em caso de falha no envio do e-mail; o job registra logs para investigação.

---

## 🧾 Fluxograma do Registro

```mermaid
flowchart TD
    A[Cliente envia POST /api/auth/register] --> B[RegisterRequest valida campos]
    B -->|Falha| B1[Retorna 422 com mensagens]
    B -->|Sucesso| C[RegisterUserDTO normaliza dados]
    C --> D[RegisterUserService]
    D --> E{CPF válido?}
    E -->|Não| E1[422 - CPF inválido]
    E -->|Sim| F{E-mail/CPF já existem?}
    F -->|Sim| F1[409 - Conflito]
    F -->|Não| G[Cria usuário (status pending_verification)]
    G --> H[EmailVerificationService gera token + cache]
    H --> I[Enfileira SendVerificationEmailJob]
    I --> J[Resposta 201: "Verifique seu e-mail"]
    I --> K[Worker executa job e envia e-mail via SendGrid]
```

---

## 🛠️ Checklist para Ambiente

1. **Banco migrado** (`php artisan migrate`) – inclui coluna `status` e tabela `mfa_methods`.
2. **Redis/Cache configurado** para armazenar tokens (`EMAIL_VERIFICATION_CACHE_STORE`).
3. **Fila funcional** (`QUEUE_CONNECTION`, tabela `jobs` e worker rodando).
4. **Credenciais SendGrid** (`SENDGRID_API_KEY`, `SENDGRID_FROM_EMAIL`, `APP_URL`) preenchidas.

Com isso o endpoint estará plenamente operacional.
