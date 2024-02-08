--- 
customlog: 
  - 
    format: combined
    target: /etc/apache2/logs/domlogs/jiranimall.com
  - 
    format: "\"%{%s}t %I .\\n%{%s}t %O .\""
    target: /etc/apache2/logs/domlogs/jiranimall.com-bytes_log
documentroot: /home/jiranim1/public_html
group: jiranim1
hascgi: 1
homedir: /home/jiranim1
ip: 198.23.59.221
owner: cloudkwe
phpopenbasedirprotect: 1
phpversion: inherit
port: 80
scriptalias: 
  - 
    path: /home/jiranim1/public_html/cgi-bin
    url: /cgi-bin/
serveradmin: webmaster@jiranimall.com
serveralias: mail.jiranimall.com www.jiranimall.com
servername: jiranimall.com
usecanonicalname: 'Off'
user: jiranim1
