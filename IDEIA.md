---

# 📄 Plugin WordPress – Declaração de Vínculo Empregatício Digital

## 1. Objetivo do Plugin

Criar um **plugin WordPress** capaz de **emitir declarações de vínculo empregatício de forma digital, segura e oficial**, destinadas à **Prefeitura**, para fins como:

* Financiamentos
* Matrículas escolares
* Processos judiciais
* Outros fins legais

O sistema deve permitir **solicitação pelo servidor**, **geração automática de PDF**, **assinatura**, **aprovação por responsáveis** e **controle de status**.

---

## 2. Papéis de Usuário

### 👤 Solicitante

* Usuário autenticado no WordPress
* Solicita a declaração
* Preenche parte das informações
* Pode assinar ou baixar o PDF
* Acompanha o status (pendente / aprovado / reprovado)

### 👨‍💼 Responsável (Aprovador)

* Usuário(s) pré-definido(s) pelo administrador
* Recebe os PDFs para análise
* Pode:

  * Aprovar (assinando e reenviando o PDF)
  * Reprovar (com opção de justificativa)

### 👨‍💻 Administrador

* Configura o plugin
* Define textos fixos
* Define variáveis fixas
* Configura imagem de fundo
* Define responsáveis pela aprovação

---

## 3. Fluxo do Sistema

1. Usuário faz **login** no WordPress
2. Usuário acessa a página **“Solicitar Declaração”**
3. Sistema:

   * Puxa automaticamente:

     * **<MATRICULA>** → username (login imutável)
     * **<NOME_COMPLETO>** → display_name
     * **<DATA>** → data de criação do PDF
4. Usuário preenche os campos obrigatórios
5. Sistema gera o **PDF**
6. Usuário pode:

   * Assinar manualmente (desenho)
   * Ou baixar para assinar digitalmente
7. PDF fica com status **PENDENTE**
8. Responsável recebe o documento
9. Responsável:

   * Assina e aprova → status **APROVADO**
   * Ou reprova → status **REPROVADO**
10. PDFs ficam armazenados em `/wp-content/uploads/`

---

## 4. Variáveis do Documento

### 🔹 Variáveis Automáticas (WordPress / Sistema)

| Variável          | Origem                      |
| ----------------- | --------------------------- |
| `<MATRICULA>`     | username (login do usuário) |
| `<NOME_COMPLETO>` | display_name                |
| `<DATA>`          | data de geração do PDF      |

---

### 🔹 Variáveis Preenchidas pelo Usuário

| Variável            |
| ------------------- |
| `<estado_civil>`    |
| `<CPF>`             |
| `<RG>`              |
| `<CARGO>`           |
| `<JORNADA_SEMANAL>` |

---

### 🔹 Variáveis Fixas (Administrador)

| Variável                        |
| ------------------------------- |
| `<CNPJ_DA_EMPRESA>`             |
| `<CIDADE>`                      |
| `<RESPONSÁVEL_PELA_DECLARAÇÃO>` |
| `<CARGO_DO_RESPONSÁVEL>`        |
| Nome da Prefeitura              |
| Texto padrão da declaração      |

---

## 5. Texto Padrão do Documento (Editável)

O texto abaixo será **padrão**, porém:

* Totalmente **editável pelo administrador**
* Permite **alterar a ordem das variáveis**
* Permite **editar texto fixo**

```
DECLARAÇÃO DE VÍNCULO EMPREGATÍCIO

Declaramos, para os devidos fins, a quem possa interessar, em especial à Prefeitura Municipal de Três Corações – MG, que o(a) Sr.(a) <NOME_COMPLETO>, Brasileiro(a), <estado_civil>, inscrito(a) no CPF sob o nº <CPF> e no RG nº <RG>, matrícula nº <MATRICULA>, mantém vínculo empregatício com a Prefeitura Municipal de Três Corações, inscrita no CNPJ sob o nº <CNPJ_DA_EMPRESA>.

O(a) referido(a) empregado(a) exerce a função de <CARGO>, com jornada de trabalho de <JORNADA_SEMANAL> horas semanais.

Declaramos que as informações acima são verdadeiras, firmando a presente declaração para que produza os efeitos legais necessários.

<CIDADE>, <DATA>

<RESPONSÁVEL_PELA_DECLARAÇÃO>
<CARGO_DO_RESPONSÁVEL>
```

---

## 6. PDF e Layout

* Geração automática de **PDF**
* Suporte a **imagem PNG de fundo**

  * Ex.: papel timbrado
* Texto sobreposto à imagem
* Posicionamento das variáveis **configurável pelo administrador**
* Fonte, tamanho e alinhamento configuráveis (opcional)

---

## 7. Assinatura

### Opções de assinatura do solicitante:

* ✍️ Assinatura manuscrita (canvas)
* ⬇️ Download para assinatura digital externa

### Assinatura do responsável:

* Upload do PDF assinado
* Documento final substitui o anterior

---

## 8. Status do Documento

| Status    | Descrição               |
| --------- | ----------------------- |
| Pendente  | Aguardando análise      |
| Aprovado  | Assinado e validado     |
| Reprovado | Negado pelo responsável |

---

## 9. Armazenamento

* PDFs salvos em:

  ```
  /wp-content/uploads/declaracoes/
  ```
* Organização por:

  * Usuário
  * Ano/Mês (opcional)

---

## 10. Segurança (Recomendado)

* Apenas usuários autenticados solicitam
* Apenas responsáveis aprovam
* PDFs protegidos por permissão
* Logs de:

  * Data da solicitação
  * Aprovação/reprovação
  * Usuário responsável

---
