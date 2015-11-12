# ACL_auth

Esta é uma library de controle de permissões de usuários (ACL) para o framework CodeIgniter. Protegido por leis internacionais de software e pela lei de Deus. ;)

## Requisitos:

1. Carregar as libraries "database" e "session"

## Banco

O sql contém cinco tabelas que administram os usuários, permissões e recursos a serem utilizados.

1. USER - tabela de usuários
2. ROLE_DATA - tabela de níveis de usuários
3. PERM_DATA - tabela de recursos
4. USER_ROLES - tabela de relacionamento usuários x níveis
5. ROLE_PERMS - tabela de relacionamento níveis x recursos

## Documentação

Para utilizar, é muito simples. Muito simples mesmo!

1. Adicionar a library "acl_auth" no arquivo autoload: `$autoload['libraries'] = array('acl_auth');`
2. No construtor do controller, é onde você verifica se o usuário possui acesso a um determinado recurso: `if( $this->acl_auth->restrict_access() )`. O método retorna um valor booleano.
3. Caso seja necessário escrever um método que seja ignorada pela ACL, basta utilizar a annotation `@ignoreACL` nos comentários do método.

Eu te disse que era muito simples.
