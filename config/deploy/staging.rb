# Server details:
server 'idran.geeksoc.org', user: 'deploy', port: 22, roles: %w{web app db}

set :deploy_to, '/var/www/applications/staging/gas-api'
