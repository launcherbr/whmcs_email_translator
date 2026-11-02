# WHMCS Email Template Translator

Addon administrativo para importar traduções de templates de e-mail do WHMCS usando JSON. O pacote inclui uma tradução `portuguese-br` extraída dos dados fornecidos, um modelo em inglês em `translations/english-model.json` e um modelo gerado a partir dos templates padrão da instalação.

## Instalação

1. Copie a pasta `whmcs_email_translator` para `modules/addons/` da instalação WHMCS.
2. No painel administrativo, acesse **System Settings > Addon Modules**.
3. Ative **WHMCS Email Template Translator** e conceda acesso à função administrativa desejada.
4. Abra o addon para importar `translations/portuguese-br.json` ou gerar um modelo em inglês atualizado para esta instalação. O modelo original também está disponível em `translations/english-model.json`.

## Formato JSON

```json
{
  "format": "whmcs-email-templates",
  "version": 1,
  "language": "portuguese-br",
  "templates": [
    {
      "type": "invoice",
      "name": "Invoice Created",
      "subject": "Fatura do Cliente",
      "message": "<p>Prezado(a) {$client_name},</p>",
      "plaintext": 0
    }
  ]
}
```

O nome e o tipo identificam o template padrão em inglês da instalação. O addon usa esses campos para criar a variante no idioma indicado ou atualizar a tradução já existente. Os templates padrão não são modificados. O campo `plaintext` é opcional; se omitido, o valor do template padrão é mantido.

O botão **Baixar modelo JSON em inglês** gera uma base com os templates padrão existentes na instalação. Preencha `language` com o código do idioma configurado no WHMCS e traduza `subject` e `message` antes de importar. Preserve as variáveis e as instruções Smarty do WHMCS, como `{$client_name}` e `{if ...}`.

O arquivo estático `translations/english-model.json` contém os 91 templates do dump original fornecido. Como ele começa com `language` vazio, informe o idioma de destino antes de importá-lo.

## Tradução `portuguese-br`

O JSON incluído contém os templates originais encontrados no dump inglês e as traduções portuguesas correspondentes encontradas no segundo dump. Os 12 templates sem tradução existente foram traduzidos para completar a coleção. Os templates não disponíveis na instalação de destino são ignorados e informados no resultado da importação.
