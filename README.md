A microservice de autenticação completo com MFA precisa de documentação clara para qualquer pessoa subir o ambiente localmente. Abaixo você encontra tudo que é necessário para compilar, executar e testar o **ms-auth**.

# ms-auth

Microserviço de autenticação com MFA para APIs que precisam controlar cadastro, login e múltiplos fatores de verificação (e-mail, SMS e WhatsApp) com tokens emitidos via Laravel Passport.

Este serviço entrega autenticação segura para aplicações web/mobile que desejam delegar o fluxo de registro, login, refresh de tokens e confirmação de contas através de múltiplos canais.

## Principais funcionalidades
- Registro e login de usuários com validações completas.
- Emissão, refresh e revogação de tokens OAuth2 (Laravel Passport).
- MFA com suporte aos canais E-mail, SMS e WhatsApp, incluindo verificação por link.
- Enfileiramento de envio de códigos e ativação de usuários.
- Swagger/OpenAPI disponível em `/api/documentation`.
- Observabilidade simples via logs centralizados (`storage/logs/laravel.log`).

---

## Sumário
1. [Stack e Tecnologias](#stack-e-tecnologias)
2. [Requisitos](#requisitos)
3. [Como rodar](#como-rodar-o-projeto)
    - [Quickstart](#51-setup-rápido-quickstart)
    - [Setup detalhado](#52-setup-detalhado-manual)
4. [Configuração (.env)](#configuração-env)
5. [Endpoints principais](#endpoints-e-exemplos)
6. [Fluxo de autenticação e MFA](#fluxo-de-autenticação-e-mfa)
7. [Testes](#testes)
8. [Observabilidade e logs](#observabilidade--logs)
9. [Troubleshooting](#troubleshooting)
10. [Roadmap](#roadmap)
11. [Contribuição](#contribuição)
12. [Licença](#licença)
13. [Autor](#autor--contato)

---

## Stack e Tecnologias
- **PHP 8.2** + **Laravel 12**.
- **MySQL 8** para persistência.
- **Redis 7** para cache e armazenamento de códigos MFA.
- **Docker / Docker Compose** como ambiente de execução.
- **Laravel Passport** para autenticação OAuth2.
- **Queues** usando `database` + jobs (`DispatchMfaCodeJob`).
- **Composer** para gerenciamento de dependências.

---

## Requisitos
- Docker 24+ e Docker Compose Plugin.
- Git.
- (Opcional) Make, caso deseje criar atalhos próprios.
- Portas utilizadas:
  - `80`: Nginx/Swagger/UI.
  - `3306`: MySQL.
  - `6379`: Redis.
  - `9000`: PHP-FPM interno (não exposto).

---

## Como rodar o projeto

### 5.1 Setup rápido (Quickstart)
```bash
git clone https://github.com/maxmateus/ms-auth.git
cd ms-auth
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan passport:install
```
Verifique em `http://localhost/api/documentation` se o Swagger abre; este endpoint funciona como healthcheck mínimo. Caso prefira, `curl -I http://localhost` deve retornar `200 OK`.

### 5.2 Setup detalhado (Manual)
1. **Clonar e criar `.env`**  
   ```
   git clone https://github.com/maxmateus/ms-auth.git
   cd ms-auth
   cp .env.example .env
   ```
2. **Subir containers**  
   `docker compose up -d --build` cria:
   - `app`: PHP-FPM com o código montado.
   - `web`: Nginx servindo `http://localhost`.
   - `db`: MySQL com banco `ms_auth`.
   - `redis`: cache/persistência de MFA.
3. **Instalar dependências**  
   `docker compose exec app composer install`
4. **Gerar chave do Laravel**  
   `docker compose exec app php artisan key:generate`
5. **Configurar o banco**  
   - `docker compose exec app php artisan migrate`
   - `docker compose exec app php artisan db:seed` (opcional, seeding customizado)
6. **Configurar Passport**  
   `docker compose exec app php artisan passport:install`  
   Copie as credenciais geradas (client IDs e secrets) para uso em ferramentas como Postman.
7. **Executar workers**  
   Para processar envio de MFA: `docker compose exec app php artisan queue:work`
8. **Gerar documentação Swagger** (quando editar anotações)  
   `docker compose exec app composer swagger`
9. **Encerrar ambiente**  
    `docker compose down` (usa `-v` para remover volumes/persistência).

---

## Configuração (.env)
Mantenha o `.env.example` sempre atualizado para facilitar o onboarding. Abaixo, principais variáveis (use valores fictícios em repositórios públicos).

### App
| Variável | Descrição | Exemplo |
|---|---|---|
| `APP_NAME` | Nome exibido em e-mails/logs | `MS Auth` |
| `APP_ENV` | Ambiente (`local`, `production`) | `local` |
| `APP_KEY` | Gerado por `php artisan key:generate` | `base64:...` |
| `APP_URL` | URL base exposta | `http://localhost` |
| `APP_DEBUG` | Toggle de debug | `true` |

### Banco de Dados
| Variável | Exemplo |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `db` (nome do serviço Docker) |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | `ms_auth` |
| `DB_USERNAME` | `ms_auth` |
| `DB_PASSWORD` | `ms_auth_pass` |

### Redis / Cache
| Variável | Exemplo |
|---|---|
| `REDIS_CLIENT` | `phpredis` |
| `REDIS_HOST` | `redis` |
| `REDIS_PORT` | `6379` |
| `CACHE_STORE` | `database` ou `redis` |
| `QUEUE_CONNECTION` | `database` (padrão) |

### Autenticação / Passport
| Variável | Descrição |
|---|---|
| `PASSPORT_PRIVATE_KEY` | Utilize `file://storage/oauth-private.key` após rodar `php artisan passport:keys --force` para apontar para o arquivo local. |
| `PASSPORT_PUBLIC_KEY` | `file://storage/oauth-public.key` |
| `PASSPORT_CONNECTION` | (Opcional) conexão específica para as tabelas do Passport. |

### Provedores de MFA
| Canal | Variáveis | Observações |
|---|---|---|
| E-mail | `SENDGRID_API_KEY`, `SENDGRID_FROM_EMAIL`, `SENDGRID_FROM_NAME` | Configure o driver `MAIL_MAILER=log` para testes locais e evite envio real. |
| SMS | `ZENVIA_API_TOKEN`, `ZENVIA_FROM` | Use sandbox fornecido pelo provedor ou desative o job de envio real durante desenvolvimento. |
| WhatsApp | `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` | Necessário número habilitado na Meta Cloud API. |

> Dica: em desenvolvimento, mantenha `MAIL_MAILER=log` e implemente “drivers fake” para SMS/WhatsApp (por exemplo, logando códigos ao invés de enviar).

---

## Endpoints e exemplos

### Autenticação

#### Login
`POST /api/auth/login`
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "secret123"
  }'
```
Resposta (200):
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJh...",
  "user": {
    "id": 1,
    "name": "Usuário",
    "email": "user@example.com"
  }
}
```

#### Refresh token
`POST /api/auth/refresh`
```bash
curl -X POST http://localhost/api/auth/refresh \
  -H "Authorization: Bearer <token_atual>"
```

#### Registro
`POST /api/auth/register` — aceita todos os campos de perfil do usuário (`name`, `email`, `password`, endereço etc.) e retorna mensagem de sucesso.

### MFA

#### Solicitar código
`POST /api/mfa/send`
```bash
curl -X POST http://localhost/api/mfa/send \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{ "method": "email", "destination": "user@example.com" }'
```

#### Validar código
`POST /api/mfa/verify`
```bash
curl -X POST http://localhost/api/mfa/verify \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{ "method": "email", "destination": "user@example.com", "code": "123456" }'
```

#### Métodos vinculados
`GET /api/mfa/methods` — retorna lista de métodos e status (`verified`).

> Consulte o Swagger em `/api/documentation` para ver todos os endpoints, corpos e respostas modelados.

---

## Fluxo de autenticação e MFA

```mermaid
flowchart LR
    A[Usuário envia credenciais] --> B[Login /auth/login]
    B -->|token emitido| C[Aplicativo inicia MFA com /mfa/send]
    C --> D[Usuário recebe código (email/sms/whatsapp)]
    D --> E[/mfa/verify|Confirma código]
    E -->|método verificado| F[Usuário liberado para endpoints protegidos]
    F --> G[/auth/refresh para prolongar sessão]
```

1. Usuário efetua login e recebe token Passport.
2. Caso o método e-mail não esteja verificado, o serviço exige MFA.
3. Código enviado via canal escolhido.
4. Após validação, o usuário é ativado e pode acessar recursos protegidos.
5. Tokens podem ser renovados via `/auth/refresh`.

---

## Testes
- `docker compose exec app php artisan test` — executa a suíte de testes.
- Para cenários futuros sem testes implementados, utilize a seção de Roadmap abaixo para acompanhar a cobertura planejada.

---

## Observabilidade / Logs
- Logs ficam em `storage/logs/laravel.log`.  
  ```bash
  docker compose exec app tail -f storage/logs/laravel.log
  ```
- Ajuste o nível via `LOG_LEVEL` no `.env`.
- Para debug rápido durante desenvolvimento, configure `LOG_CHANNEL=stderr` e veja a saída no container (`docker compose logs app -f`).

---

## Troubleshooting
1. **Porta 80 ocupada**  
   - Ajuste o mapeamento em `docker-compose.yml` (ex.: `8080:80`) ou libere a porta antes de subir o Nginx.
2. **Erro de permissão em `storage` ou `bootstrap/cache`**  
   - Execute `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.
3. **`SQLSTATE[HY000] [2002] Connection refused`**  
   - Certifique-se de que o serviço `db` está saudável (`docker compose ps`) e use `DB_HOST=db`.
4. **`APP_KEY` ausente**  
   - Rode `docker compose exec app php artisan key:generate`.
5. **Passport falha por falta de chaves**  
   - Execute `docker compose exec app php artisan passport:install` e configure `PASSPORT_PRIVATE_KEY=file://storage/oauth-private.key`.
6. **Fila não processa códigos MFA**  
   - Inicie o worker com `docker compose exec app php artisan queue:work` ou utilize `php artisan queue:listen` em modo desenvolvimento.
7. **Swagger retorna 404 em `/docs`**  
   - Gere novamente o JSON: `docker compose exec app composer swagger`. Verifique se `docs/openapi/api-docs.json` existe.
8. **Mail/SMS enviando de verdade no desenvolvimento**  
   - Use `MAIL_MAILER=log` e configure provedores em modo sandbox ou mocks específicos.

---

---

## Autor / Contato
- **Max Mateus** — Desenvolvedor Back-end.
- Portfólio: [https://maxmateus.com.br](https://maxmateus.com.br)
- GitHub: [https://github.com/maxmateus](https://github.com/maxmateus)
- LinkedIn: [https://www.linkedin.com/in/maxmateus](https://www.linkedin.com/in/maxmateus)
