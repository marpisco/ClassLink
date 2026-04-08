<?php
    require_once(__DIR__ . '/../vendor/autoload.php');
    use League\OAuth2\Client\Provider\GenericProvider;

    // Email configuration
    $mail = array(
        'ativado' => true,
        'servidor' => 'smtp.gmail.com',
        'porta' => 465,
        'autenticacao' => true,
        // caso a autenticação seja por starttls, usar PHPMailer::ENCRYPTION_STARTTLS
        // caso a autenticação seja por ssl, usar PHPMailer::ENCRYPTION_SMTPS
        // caso não seja necessário autenticação, por false na opção autenticacao, e não importar-se para os outros
        'tipodeseguranca' => 'PHPMailer::ENCRYPTION_STARTTLS ou PHPMailer::ENCRYPTION_SMTPS',
        'username' => '',
        'fromname' => 'Reserva de Salas',
        'mailfrom' => '',
        'password' => ''
    );

    // Configuração da DB
    $db = array(
        'tipo' => 'mysql', // deixar assim
        'servidor' => 'localhost',
        'user' => 'reservasalas',
        'password' => 'salaspass',  // USAR SEMPRE PASSWORDS FORTES
        'db' => 'reservasalas',
        'porta' => 3306
    );

    // Configuração do fornecedor OAuth2
    $authProvider = 'Microsoft';
    $provider = new GenericProvider([
        'urlAuthorize'            => 'https://authentik.devenv.marcopisco.com/application/o/authorize/',
        'urlAccessToken'          => 'https://authentik.devenv.marcopisco.com/application/o/token/',
        'urlResourceOwnerDetails' => 'https://authentik.devenv.marcopisco.com/application/o/userinfo/',
        'clientId'     => 'clientid',  // CHANGE THIS
        'clientSecret' => 'clientsecret',  // CHANGE THIS and keep it secret
        'redirectUri'  => 'https://' . $_SERVER['HTTP_HOST'] . '/login'
    ]);
?>