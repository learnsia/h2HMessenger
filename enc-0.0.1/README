h2H Messenger

***************************************
***************************************

THIS IS AN ALPHA RELEASE AND NOT MEANT

FOR PRODUCTION USE.

***************************************
***************************************
h2H Messenger was conceived as I was using my other script - GnuPG Secure Form (https://github.com/learnsia/GnuPG-Secure-Form).  It allows someone to send me a message encrypted with a GnuPG key without having to distribute the key to the sender or a bunch of typical PKI stuff.  They simply visit the website over an SSL channel, fill out the form, send the message and attachments and it arrives to me encrypted and I can worry about the decryption, not my clients.

However, if my client wanted to communicate securely with me, I'd have to get them setup with a Public and Private key and there was no telling which email client they were used to using.  There was too much headache of supporting various clients (people), email clients, or having them switch to a specific email client.

I decided to create a similar tool as GnuPG Secure Form, except allow two-way encrypted communication of the data while at rest and being sent back and forth to a server.  The hosting provider I was using at the time, didn't allow shell access so using GnuPG was out.  I'm using PHP's OpenSSL native functions and a third-party library to accomplish this task.  h2H Messenger doesn't, in any way shape or form, attempt to compete with a tool like PGP or GnuPG, and never will.  It is for a few people to exchange sensitive information and keep the communication secured.  It is especially useful for a consultant to communicate with their clients without the overhead of the clients having maintain keys and be taught the complexities of PKI.  All messages are encrypted in the database with the sender and receivers RSA 2048-bit public keys.  This is a non-escrow version so there are no master keys.  The source is open for review.

Instead of outlining everything all over again, here is the presentation I presented at the NoVa Hackers meeting:

https://docs.google.com/presentation/d/1CWS2j5wba_taYZySYyl6CWeAhu5pKITu951KxRE6u98/edit

This application is meant to be run on an internal network or a host with limited services between a user (e.g. a consultant) and their client(s).  The private key and randomly created passphrase, for the private key, are encrypted and stored in a DB on the server, but protected by the user's password that is used to authenticate to the h2H Messenger application.  When the user authenticates AND passes the two-factor authentication, the private key and the random passphrase are stored in PHP session variables.  However, those variables are encrypted at-rest (during the login session) and only decrypted when called in the application (where ever you setup the location of the "phpsec::$_dsn = 'filesystem:/var/rand/data';") take a look inside those files to ensure it is encrypted.  The key for the encrypted session variables are stored as a cookie on the client and changed every 30 seconds thanks to PHPSec http://phpseclib.com/.

Known Issue:

The session file isn't properly being removed which has been reported:

https://github.com/phpsec/phpSec/issues/98#issuecomment-7512318

Note the PHP garbage collection can resolve this issue.  Also, the contents of the session variables are encrypted.

"I'm unable to fix this at this time, due to how the PHP session works. Calling session_destoy() tries to destroy a session that don't exist and then the cleanup code that should be run is ignored. However the build in garbage collection in PHP should take care of leftover session files.

    session.gc_probability = 1
    session.gc_divisor = 100
    session.gc_maxlifetime = 1440"


TODO: Add an admin screen to add more SMS gateways
Other stuff based on feedback or bug oops. :)

PHP OpenSSL functions & Mcrypt
PHPSeclib (http://phpseclib.com/)

GNU GPL V3.

INSTALLATION:

1. Extract the file to a web directory.

tar xzvf <package version>

2. Configure the file "function.php" to your environment.

3. Create the database:

mysqladmin -u <user> -p create h2HMessenger

4. Import the DB schema:

mysql -u <user> -p h2HMessenger < enc.sql

(If you use a tool such as PHPMYAdmin, then create the DB in that manner)

Two-factor authentication:

For the two-factor authentication, you can simply use SMS.  This will send the code to the user's cell phone, if their service provider supports it.  A few known SMS Gateways have been added to the databse.

If you want your users to receive a phone call then, you'll need to install Asterisk.  THIS IS ALPHA, so it runs on the same system as the php app.  Asterisk listens on the loopback interface and no inbound connections allowed.  Only IAX2 traffic to my VOIP provider is allowed out.  The next release will decouple the servers and the call file generated to initiate the phone call will be performed over an SSL connection to a waiting PHP app where the hardened Asterisk server resides.

I used this tutorial to install Asterisk:

http://ethertubes.com/install-asterisk-1-8-from-source-on-ubuntu-11-10/

Here is my tutorial that explains how the authentication works.

https://secure.sukkha.info/asterisk-2fa.html

