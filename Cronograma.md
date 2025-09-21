# 📋 Cronograma de Desenvolvimento - MS-Auth

> **Projeto:** Microserviço de Autenticação com MFA  
> **Duração:** 30 dias  
> **Framework:** Laravel + JWT + MFA

---

## 🏗️ Fase 1 – Preparação (Dias 1-2)

### Configuração Inicial
- [ ] Criar repositório `ms-auth` no GitHub
- [ ] Criar projeto Laravel: composer create-project laravel/laravel ms-auth

### Configuração do Ambiente
- [ ] Configurar arquivo `.env`:
  - Banco de dados MySQL
  - Configuração inicial de cache (Redis pode vir depois)
- [ ] Subir ambiente com Docker Compose:
  - MySQL
  - PHP
  - Nginx

---

## 🔐 Fase 2 – Estrutura de Autenticação (Dias 3-5)

### Laravel Passport
- [ ] Instalar e configurar Laravel Passport:
  composer require laravel/passport
  php artisan migrate
  php artisan passport:install
- [ ] Configurar `AuthServiceProvider` para usar Passport
- [ ] Implementar geração de tokens JWT via Passport

### Migrations
- [ ] Criar migrations básicas:
  - `users`
  - `password_resets`
  - `roles` e `permissions` (opcional - usar `spatie/laravel-permission`)

## 🛠️ Fase 3 – Endpoints Básicos (Dias 6-9)

### AuthController
- [ ] Implementar endpoints de autenticação:
  - `POST /register` → Cadastro de usuário
  - `POST /login` → Gera token JWT
  - `POST /logout` → Revoga token
  - `POST /refresh` → Gera novo token

### Validações e Segurança
- [ ] Adicionar validações:
  - Senha forte
  - Email único
- [ ] Implementar rate limiting em login

## 🛡️ Fase 4 – MFA (Multi-Factor Authentication) (Dias 10-18)

### Estrutura de Dados
- [ ] Criar tabela `user_mfa_methods`:
  - Armazenar preferência: email, SMS, WhatsApp
- [ ] Criar tabela `user_mfa_tokens`:
  - Armazenar OTP + expiração

### Implementação de Canais
- [ ] Implementar envio de OTP:
  - **Email** → `laravel-notification`
  - **SMS** → Twilio/Zenvia/AWS SNS
  - **WhatsApp** → Twilio WhatsApp API ou Meta Cloud API

### Fluxo MFA
- [ ] Implementar fluxo completo:
  1. Usuário faz login com credenciais
  2. API gera código OTP e envia pelo canal configurado
  3. `POST /verify-mfa` → Valida código e libera acesso

## 🔒 Fase 5 – Segurança Avançada (Dias 19-23)

### Proteção de Dados
- [ ] Salvar OTP hashado no banco (não texto puro)
- [ ] Configurar expiração de OTP (ex: 5 minutos)

### Prevenção de Ataques
- [ ] Bloquear login após X tentativas falhas (rate limit)
- [ ] Criar tabela `login_attempts` para auditoria
- [ ] Implementar notificação de login suspeito:
  - Outro país/dispositivo
  - Horário incomum

## 📊 Fase 6 – Observabilidade e Escalabilidade (Dias 24-28)

### Cache e Filas
- [ ] Integrar Redis para cache de OTPs
- [ ] Integrar RabbitMQ para fila de envio:
  - SMS
  - WhatsApp
  - Email

### Monitoramento
- [ ] Configurar logs de auditoria (MongoDB opcional)
- [ ] Adicionar ferramentas de monitoramento:
  - Laravel Telescope
  - Sentry (opcional)

## 🚀 Fase 7 – Deploy (Dias 29-30)

### Containerização
- [ ] Containerizar aplicação com Docker
- [ ] Criar `docker-compose.yml` para produção

### CI/CD
- [ ] Configurar GitHub Actions:
  - Testes automatizados
  - Deploy automático

### Produção
- [ ] Deploy em servidor (AWS, DigitalOcean, etc.)
- [ ] Configurar HTTPS:
  - LetsEncrypt ou Cloudflare
- [ ] Realizar testes finais de segurança:
  - SQL Injection
  - XSS
  - Força bruta

## 📈 Resumo por Semana

| Semana | Fases | Foco Principal |
|--------|-------|----------------|
| **1** | 1-2 | Configuração e estrutura base |
| **2** | 3-4 | Autenticação básica e MFA |
| **3** | 5-6 | Segurança avançada e observabilidade |
| **4** | 7 | Deploy e testes finais |

## 🎯 Entregáveis Principais

- ✅ API de autenticação completa com JWT
- ✅ Sistema MFA multi-canal (Email, SMS, WhatsApp)
- ✅ Logs de auditoria e monitoramento
- ✅ Ambiente containerizado e CI/CD
- ✅ Deploy em produção com HTTPS