# Declaração de Vínculo Empregatício Digital (Plugin WordPress)

Plugin WordPress para emissão digital de **declarações de vínculo empregatício**, com:
- Solicitação pelo servidor (usuário logado)
- Geração automática de PDF
- Assinatura manuscrita do solicitante (canvas)
- Fluxo de aprovação por responsáveis
- Armazenamento dos documentos em `/wp-content/uploads/declaracoes/ANO/MES/`

---

## 1. Requisitos

- PHP compatível com WordPress atual
- WordPress instalado e em funcionamento
- Composer disponível no servidor ou ambiente de desenvolvimento

### Bibliotecas usadas (via Composer)

O plugin usa:

- `tecnickcom/tcpdf` – geração de PDF
- `setasign/fpdi` – uso de PDF como template/fundo

Essas dependências já estão configuradas no `composer.json`.

---

## 2. Instalação do plugin

1. Copie a pasta do plugin para:

   ```text
   wp-content/plugins/declaracao-vinculo-empregaticio
   ```

   Certifique-se de que dentro dessa pasta estejam, entre outros:

   - `declaracao-vinculo-empregaticio.php`
   - `composer.json`
   - `vendor/` (gerada pelo Composer)

2. Caso ainda não tenha rodado o Composer (em ambiente de desenvolvimento):

   ```bash
   cd wp-content/plugins/declaracao-vinculo-empregaticio
   composer install
   ```

   Isso instalará **TCPDF** e **FPDI** em `vendor/`.

3. No painel WordPress:

   - Acesse **Plugins → Plugins instalados**
   - Ative **Declaração de Vínculo Empregatício Digital**

---

## 3. Configuração inicial

Após ativar, será criado um menu no admin:

- **Declarações**
  - **Declarações** (lista de solicitações)
  - **Configurações** (parâmetros do documento)

### 3.1. Acessando as configurações

No painel WordPress:

- Vá em **Declarações → Configurações**

Preencha:

- **CNPJ da empresa** – `<CNPJ_DA_EMPRESA>`
- **Cidade** – `<CIDADE>`
- **Nome da Prefeitura** – usado no texto padrão
- **Responsável pela declaração** – `<RESPONSÁVEL_PELA_DECLARAÇÃO>`
- **Cargo do responsável** – `<CARGO_DO_RESPONSÁVEL>`

### 3.2. Responsáveis (aprovadores)

Na mesma tela, existe o campo:

- **Responsáveis (aprovadores)**

Selecione um ou mais usuários que poderão:

- Aprovar declarações (enviando PDF assinado)
- Reprovar declarações (informando motivo)

### 3.3. Texto padrão

Também em **Declarações → Configurações**, existe o campo:

- **Texto padrão da declaração**

Este texto é totalmente editável.  
Você pode usar as variáveis:

- `<MATRICULA>` – login do usuário (username)
- `<NOME_COMPLETO>` – `display_name` do usuário
- `<DATA>` – data da geração da declaração por extenso (ex: 15 de janeiro de 2026)
- `<estado_civil>` – preenchido no formulário
- `<CPF>` – preenchido no formulário
- `<RG>` – preenchido no formulário
- `<CARGO>` – preenchido no formulário
- `<JORNADA_SEMANAL>` – preenchido no formulário
- `<CNPJ_DA_EMPRESA>` – das configurações
- `<CIDADE>` – das configurações
- `<RESPONSÁVEL_PELA_DECLARAÇÃO>` – das configurações
- `<CARGO_DO_RESPONSÁVEL>` – das configurações

O plugin substituirá essas variáveis automaticamente na hora de gerar o PDF.

### 3.4. Papel timbrado (fundo)

Em **Imagem de fundo (papel timbrado)**:

- Clique em **Selecionar imagem**
- Você pode enviar:
  - Uma **imagem** (PNG/JPG) – será usada como fundo da página A4
  - Um **PDF** (com o layout da declaração) – o plugin usará a **primeira página** como template via FPDI

---

## 4. Páginas e shortcodes

O plugin fornece dois shortcodes:

- `[dve_solicitar_declaracao]` – formulário de solicitação
- `[dve_minhas_declaracoes]` – lista de declarações do usuário logado

### 4.1. Página “Solicitar Declaração”

1. Crie uma nova página no WordPress:
   - Título sugerido: **Solicitar Declaração**
2. No conteúdo da página, insira:

   ```text
   [dve_solicitar_declaracao]
   ```

Essa página:

- Exige que o usuário esteja logado.
- Mostra o formulário com:
  - Matrícula (login) – preenchido automaticamente (não editável)
  - Nome completo – preenchido automaticamente (não editável)
  - Estado civil
  - CPF
  - RG
  - Cargo
  - Jornada semanal (horas)
  - Campo de assinatura (canvas) opcional

Ao enviar:

- Cria uma nova declaração com status **Pendente**
- Gera o PDF (ou erro se TCPDF não estiver disponível)
- Redireciona de volta para a página com mensagem de sucesso

### 4.2. Página “Minhas Declarações”

1. Crie outra página:
   - Título sugerido: **Minhas Declarações**
2. Conteúdo:

   ```text
   [dve_minhas_declaracoes]
   ```

Essa página:

- Exibe uma tabela com as declarações do usuário logado:
  - Data
  - Status (Pendente / Aprovado / Reprovado)
  - Link para o documento
  - Motivo da reprovação (se houver)

---

## 5. Fluxo completo

### 5.1. Solicitante

- Faz login no WordPress
- Acessa a página **Solicitar Declaração**
- Preenche os dados
- Opcional: desenha a assinatura no canvas
- Envia o formulário
- A declaração é gerada e fica com status **Pendente**

### 5.2. Responsável (aprovador)

No painel admin:

- Acessa **Declarações → Declarações**
- Vê a lista de solicitações com:
  - ID
  - Solicitante
  - Data
  - Status
  - Link para o documento atual

Para uma declaração pendente, o responsável pode:

- **Aprovar**:
  - Baixa o documento
  - Assina (digitalmente ou manual + digitalização)
  - Faz upload do **PDF assinado** no campo “PDF assinado”
  - Clica em **Aprovar**
  - O PDF enviado substitui o anterior e o status vira **Aprovado**

- **Reprovar**:
  - Preenche o campo “Motivo da reprovação”
  - Clica em **Reprovar**
  - Status vira **Reprovado**
  - O motivo fica visível para o solicitante na página **Minhas Declarações**

### 5.3. Armazenamento dos arquivos

Os documentos gerados são salvos em:

```text
wp-content/uploads/declaracoes/ANO/MES/declaracao-POST_ID.pdf
```

O diretório é criado automaticamente conforme o ano/mês atual.

---

## 6. Segurança e permissões

- Apenas **usuários autenticados** podem:
  - Enviar novas declarações
  - Ver suas próprias declarações (`[dve_minhas_declaracoes]`)
- Apenas **responsáveis definidos nas configurações** podem:
  - Aprovar
  - Reprovar

Os PDFs ficam no diretório padrão de uploads do WordPress.  
Se quiser proteção adicional (por exemplo, servir PDFs apenas após checar permissão), pode-se evoluir o plugin para:

- Criar um endpoint protegido para servir o arquivo
- Bloquear acesso direto via `.htaccess` ou regras de servidor

---

## 7. Erros comuns / Dicas

- **PDF não é gerado ou arquivo fica vazio**
  - Verifique se as bibliotecas estão instaladas:
    - `vendor/` existe dentro do plugin?
    - `composer install` foi executado?
  - Verifique se a constante `TCPDF` está disponível (a biblioteca foi carregada).

- **Imagem/PDF de fundo não aparece**
  - Confirme se você selecionou a imagem/PDF na tela de configurações.
  - Verifique se o arquivo realmente existe no servidor (biblioteca de mídia).

- **Usuário não vê o menu Declarações no admin**
  - O menu é visível para usuários com permissão de administração (`manage_options`).
  - Responsáveis não precisam do menu de configurações, apenas de acesso ao menu de declarações (via admin).

---

## 8. Resumo rápido de uso

1. Ative o plugin.
2. Configure:
   - CNPJ, cidade, prefeitura, responsável, cargo.
   - Responsáveis (aprovadores).
   - Texto padrão com variáveis.
   - Imagem ou PDF de fundo.
3. Crie:
   - Página com `[dve_solicitar_declaracao]`
   - Página com `[dve_minhas_declaracoes]`
4. Oriente os usuários:
   - Acessar **Solicitar Declaração** para emitir.
   - Acompanhar em **Minhas Declarações**.
5. Responsáveis:
   - Acessam **Declarações → Declarações** no admin.
   - Aprovam enviando PDF assinado ou reprovam com motivo.

## 9. Exemplo de local da assinatura

1. Coordenadas manuais (usadas apenas se selecionar "Posição manual"):
X (mm): 80 e  Y (mm): 165