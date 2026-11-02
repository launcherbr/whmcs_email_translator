<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function whmcs_email_translator_config()
{
    return array(
        'name' => 'WHMCS Email Template Translator',
        'description' => 'Import JSON translations for WHMCS email templates.',
        'version' => '1.0.0',
        'author' => 'OpenAI',
        'language' => 'english',
        'fields' => array(),
    );
}

function whmcs_email_translator_activate()
{
    return array('status' => 'success', 'description' => 'Module activated.');
}

function whmcs_email_translator_deactivate()
{
    return array('status' => 'success', 'description' => 'Module deactivated.');
}

function whmcs_email_translator_output($vars)
{
    $messages = array();
    $downloadJson = '';
    $importResults = array();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!function_exists('check_token') || !check_token('WHMCS.admin.default')) {
            $messages[] = array('error', 'Token de segurança inválido. Atualize a página e tente novamente.');
        } elseif (isset($_POST['whmcs_email_translator_export'])) {
            try {
                $downloadJson = whmcs_email_translator_export_english_model();
            } catch (Exception $exception) {
                $messages[] = array('error', $exception->getMessage());
            }
        } elseif (isset($_POST['whmcs_email_translator_import'])) {
            if (!isset($_FILES['translation_file']) || $_FILES['translation_file']['error'] !== UPLOAD_ERR_OK) {
                $messages[] = array('error', 'Selecione um arquivo JSON de tradução válido.');
            } elseif ($_FILES['translation_file']['size'] > 10485760) {
                $messages[] = array('error', 'O arquivo excede o limite de 10 MB.');
            } else {
                $contents = file_get_contents($_FILES['translation_file']['tmp_name']);
                if ($contents === false) {
                    $messages[] = array('error', 'Não foi possível ler o arquivo enviado.');
                } else {
                    try {
                        if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
                            $contents = substr($contents, 3);
                        }
                        $payload = json_decode($contents, true);
                        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('O arquivo enviado não contém um JSON válido.');
                        }
                        $importResults = whmcs_email_translator_import($payload);
                    } catch (Exception $exception) {
                        $messages[] = array('error', $exception->getMessage());
                    }
                }
            }
        }
    }

    echo '<div class="content">';
    echo '<h2>Tradução de Templates de E-mail do WHMCS</h2>';
    echo '<p>Exporte um modelo com os textos originais em inglês, traduza-o e importe o JSON para criar ou atualizar os templates no idioma escolhido.</p>';

    foreach ($messages as $message) {
        $alertType = $message[0] === 'error' ? 'danger' : 'success';
        echo '<div class="alert alert-' . $alertType . '">' . whmcs_email_translator_escape($message[1]) . '</div>';
    }

    if ($downloadJson !== '') {
        $downloadData = base64_encode($downloadJson);
        echo '<div class="alert alert-success">Modelo JSON gerado. O download deve começar automaticamente.</div>';
        echo '<script>(function(){var raw=atob(' . json_encode($downloadData) . ');var bytes=new Uint8Array(raw.length);for(var i=0;i<raw.length;i++){bytes[i]=raw.charCodeAt(i);}var url=URL.createObjectURL(new Blob([bytes],{type:"application/json"}));var link=document.createElement("a");link.href=url;link.download="whmcs-email-templates-english-model.json";link.click();setTimeout(function(){URL.revokeObjectURL(url);},1000);}());</script>';
    }

    if ($importResults) {
        echo '<h3>Resultado da importação</h3><ul>';
        foreach ($importResults['summary'] as $summary) {
            echo '<li>' . whmcs_email_translator_escape($summary) . '</li>';
        }
        echo '</ul>';
        if ($importResults['errors']) {
            echo '<details><summary>Templates ignorados (' . count($importResults['errors']) . ')</summary><ul>';
            foreach (array_slice($importResults['errors'], 0, 100) as $error) {
                echo '<li>' . whmcs_email_translator_escape($error) . '</li>';
            }
            echo '</ul></details>';
        }
    }

    $token = function_exists('generate_token') ? generate_token('plain') : '';
    echo '<div class="row"><div class="col-md-6"><h3>Modelo em inglês</h3>';
    echo '<p>Baixe os templates padrão desta instalação para usá-los como base de uma tradução.</p>';
    echo '<form method="post"><input type="hidden" name="token" value="' . whmcs_email_translator_escape($token) . '">';
    echo '<button class="btn btn-primary" type="submit" name="whmcs_email_translator_export" value="1">Baixar modelo JSON em inglês</button></form></div>';
    echo '<div class="col-md-6"><h3>Importar tradução</h3>';
    echo '<p>O idioma deve ser o código usado no WHMCS, por exemplo <code>portuguese-br</code>.</p>';
    echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="token" value="' . whmcs_email_translator_escape($token) . '">';
    echo '<p><label for="translation_file">Arquivo JSON</label><input class="form-control" id="translation_file" name="translation_file" type="file" accept=".json,application/json" required></p>';
    echo '<button class="btn btn-warning" type="submit" name="whmcs_email_translator_import" value="1" onclick="return confirm(\'Importar os templates para o idioma indicado no JSON? Os templates originais em inglês não serão alterados.\')">Importar tradução</button></form></div></div>';
    echo '<p class="text-muted">A importação atualiza templates localizados existentes ou cria novos com base no template padrão correspondente. Templates ausentes nesta instalação são ignorados e listados no resultado.</p>';
    echo '</div>';
}

function whmcs_email_translator_export_english_model()
{
    if (!class_exists('WHMCS\\Database\\Capsule')) {
        throw new Exception('O banco de dados do WHMCS não está disponível.');
    }

    $rows = \WHMCS\Database\Capsule::table('tblemailtemplates')
        ->where('language', '')
        ->orderBy('id')
        ->get(array('type', 'name', 'subject', 'message', 'plaintext'));

    $templates = array();
    foreach ($rows as $row) {
        $templates[] = array(
            'type' => (string) $row->type,
            'name' => (string) $row->name,
            'subject' => (string) $row->subject,
            'message' => (string) $row->message,
            'plaintext' => (int) $row->plaintext,
        );
    }
    if (!$templates) {
        throw new Exception('Nenhum template padrão em inglês foi encontrado.');
    }

    $json = json_encode(array(
        'format' => 'whmcs-email-templates',
        'version' => 1,
        'language' => '',
        'templates' => $templates,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new Exception('Não foi possível gerar o modelo JSON.');
    }
    return $json;
}

function whmcs_email_translator_import($payload)
{
    if (!isset($payload['format']) || $payload['format'] !== 'whmcs-email-templates'
        || !isset($payload['version']) || (int) $payload['version'] !== 1) {
        throw new Exception('Formato de arquivo incompatível. Use um JSON whmcs-email-templates versão 1.');
    }
    $language = isset($payload['language']) ? strtolower(trim((string) $payload['language'])) : '';
    if ($language === '' || strlen($language) > 64 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $language)) {
        throw new Exception('O JSON deve informar um idioma válido, como portuguese-br.');
    }
    if (!isset($payload['templates']) || !is_array($payload['templates']) || !$payload['templates']) {
        throw new Exception('O JSON não contém templates para importar.');
    }
    if (count($payload['templates']) > 1000) {
        throw new Exception('O arquivo contém mais de 1000 templates.');
    }

    $validated = array();
    $seen = array();
    foreach ($payload['templates'] as $template) {
        if (!is_array($template)) {
            throw new Exception('Cada template deve ser um objeto JSON.');
        }
        foreach (array('type', 'name', 'subject', 'message') as $field) {
            if (!isset($template[$field]) || !is_string($template[$field])) {
                throw new Exception('Todos os templates devem incluir type, name, subject e message como texto.');
            }
        }
        $type = trim($template['type']);
        $name = trim($template['name']);
        $subject = $template['subject'];
        $message = $template['message'];
        if ($type === '' || $name === '' || trim($subject) === '' || trim($message) === '') {
            throw new Exception('Tipo, nome, assunto e mensagem não podem ficar vazios.');
        }
        if (strlen($type) > 255 || strlen($name) > 255 || strlen($subject) > 65535 || strlen($message) > 65535) {
            throw new Exception('Um template excede o tamanho máximo permitido pelo WHMCS.');
        }
        $key = strtolower($type) . "\0" . strtolower($name);
        if (isset($seen[$key])) {
            throw new Exception('O JSON contém templates repetidos: ' . $name . '.');
        }
        $seen[$key] = true;
        $validated[] = array(
            'type' => $type,
            'name' => $name,
            'subject' => $subject,
            'message' => $message,
            'plaintext' => isset($template['plaintext']) ? ((int) $template['plaintext'] === 1 ? 1 : 0) : null,
        );
    }

    if (!class_exists('WHMCS\\Database\\Capsule')) {
        throw new Exception('O banco de dados do WHMCS não está disponível.');
    }

    $inserted = 0;
    $updated = 0;
    $errors = array();
    \WHMCS\Database\Capsule::connection()->transaction(function () use ($validated, $language, &$inserted, &$updated, &$errors) {
        foreach ($validated as $template) {
            $base = \WHMCS\Database\Capsule::table('tblemailtemplates')
                ->where('type', $template['type'])
                ->where('name', $template['name'])
                ->where('language', '')
                ->first();
            if (!$base) {
                $errors[] = $template['name'] . ': template original não encontrado nesta instalação.';
                continue;
            }

            $matches = \WHMCS\Database\Capsule::table('tblemailtemplates')
                ->where('type', $template['type'])
                ->where('name', $template['name'])
                ->where('language', $language)
                ->get(array('id'));
            if (count($matches) > 1) {
                throw new Exception('Há mais de uma tradução existente para o template ' . $template['name'] . ' no idioma ' . $language . '.');
            }

            $plaintext = $template['plaintext'] === null ? (int) $base->plaintext : $template['plaintext'];
            $values = array(
                'subject' => $template['subject'],
                'message' => $template['message'],
                'plaintext' => $plaintext,
                'updated_at' => date('Y-m-d H:i:s'),
            );

            if (count($matches) === 1) {
                \WHMCS\Database\Capsule::table('tblemailtemplates')
                    ->where('id', (int) $matches[0]->id)
                    ->update($values);
                $updated++;
                continue;
            }

            $record = (array) $base;
            unset($record['id']);
            $record['language'] = $language;
            $record['subject'] = $template['subject'];
            $record['message'] = $template['message'];
            $record['plaintext'] = $plaintext;
            $record['created_at'] = date('Y-m-d H:i:s');
            $record['updated_at'] = $record['created_at'];
            \WHMCS\Database\Capsule::table('tblemailtemplates')->insert($record);
            $inserted++;
        }
    });

    $summary = array(
        'Idioma: ' . $language,
        'Templates criados: ' . $inserted,
        'Templates atualizados: ' . $updated,
        'Templates ignorados: ' . count($errors),
    );
    return array('summary' => $summary, 'errors' => $errors);
}

function whmcs_email_translator_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
