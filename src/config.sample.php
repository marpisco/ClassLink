<?php
    require_once(__DIR__ . '/../vendor/autoload.php');
    use League\OAuth2\Client\Provider\GenericProvider;
    /* Ficheiro de configuração
       Mais informação sobre o ficheiro de configuração está na documentação.
    */

    // Configuração da Base de Dados
    $db = array(
        'tipo' => 'mysql',         // Apenas há suporte a MYSQL (mysql = mariadb) por enquanto
        'servidor' => 'localhost',
        'user' => 'reservasalas',
        // Defina uma password forte antes de usar em produção e restrinja as permissões deste utilizador da DB ao mínimo necessário.
        'password' => '',
        'db' => 'reservasalas',
        'porta' => 3306
    );
        
    // Configuração do servidor de email
    $mail = array(
        'ativado' => true,
        'servidor' => 'smtp.gmail.com',
        'porta' => 465,
        'autenticacao' => true,
        // Tipo de Segurança:
        // - Para STARTTLS (normalmente porta 587), usar \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
        // - Para SMTPS/SSL (normalmente porta 465), usar \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        // - Se não for necessária autenticação, definir 'autenticacao' => false e ajustar esta opção conforme necessário
        'tipodeseguranca' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
        'username' => '',
        'fromname' => 'Reserva de Salas',
        'mailfrom' => '',
        'password' => ''
    );

    // Configuração do Fornecedor de Autenticação (OAuth 2.0)
    $provider = new GenericProvider([
        'urlAuthorize'            => 'https://authentik.devenv.marcopisco.com/application/o/authorize/',
        'urlAccessToken'          => 'https://authentik.devenv.marcopisco.com/application/o/token/',
        'urlResourceOwnerDetails' => 'https://authentik.devenv.marcopisco.com/application/o/userinfo/',
        'clientId'     => 'clientid',
        'clientSecret' => 'clientsecret',
        'redirectUri'  => 'https://' . $_SERVER['HTTP_HOST'] . '/login'
    ]);
?>