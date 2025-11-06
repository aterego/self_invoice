<?php
putenv('MAIL_TRANSPORT=smtp');           // use SMTP
putenv('SMTP_HOST=angelmoversinc.ca');   // per cPanel
putenv('SMTP_PORT=465');                 // per cPanel
putenv('SMTP_SECURE=ssl');               // 465 -> ssl (not tls)
putenv('SMTP_USER=info@angelmoversinc.ca');
putenv('SMTP_PASS=20Cosa25Nostra#');
putenv('SMTP_AUTH=1');
putenv('SMTP_DEBUG=1');
putenv('MAIL_FROM=info@angelmoversinc.ca');  // match the login
putenv('MAIL_TO=talis.qualis@gmail.com');           // where you want admin copies to go
