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
        'password' => 'salaspass', 
        'db' => 'reservasalas',
        'porta' => 3306
    );
        
    // Configuração do servidor de email
    $mail = array(
        'ativado' => true,
        'servidor' => 'smtp.gmail.com',
        'porta' => 465,
        'autenticacao' => true,
        // Tipo de Segurança: Explicação:
        /// caso a autenticação seja por starttls, usar PHPMailer::ENCRYPTION_STARTTLS
        /// caso a autenticação seja por ssl, usar PHPMailer::ENCRYPTION_SMTPS
        /// caso não seja necessário autenticação, por false na opção autenticacao, e não importar-se para os outros
        'tipodeseguranca' => 'PHPMailer::ENCRYPTION_STARTTLS ou PHPMailer::ENCRYPTION_SMTPS',
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