<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Library de ACL para o Codeigniter
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category		Libraries
 * @author 			Vlamir Santo
 * @version 		1.0
 */

class Acl_auth
{
	private $ci;

	public function __construct()
	{
		$this->ci =& get_instance();
	}

	/**
	* Método de login. Autentica os dados do usuário e senha e salva os mesmos na sessão
	*
	* @param array | array com login e senha do usuário
	* @return object | boolean false
	*/
	public function login( array $dados )
	{
		$result = $this->_authenticate( $dados['username'], $dados['password'] );
		if( $result ) {
			// Gravando dados na sessão
			$sess_array = array(
				'id_user'	=> $result->id_user,
				'username'	=> $result->username,
				'email'		=> $result->email,
				'activated'	=> $result->activated
			);
			$this->ci->session->set_userdata( 'logged_in', $sess_array );

			// Gravando IP e data de acesso
			$acesso = array(
				'last_ip'		=> $this->get_ip_address(),
				'last_login'	=> date( 'Y-m-d H:s:i' )
			);
			$this->ci->db->update( 'user', $acesso , array( 'id_user' => $result->id_user ) );

			return $result;
		} else {
			$this->ci->session->set_flashdata( 'check_database', 'Email / Login ou Senha inválidos' );
		}

		return false;
	}

	/**
	* Método de logout
	*
	* @return boolean
	*/
	public function logout()
	{
		$this->ci->session->sess_destroy();
		return $this->logged_in();
	}

	/**
	* Método de verificação se usuário está logado ou não
	*
	* @return boolean
	*/
	public function logged_in()
	{
		return (bool) $this->ci->session->userdata( 'logged_in' );
	}

	/**
	* Método de verificação da regra de ACL.
	* Verifica se o perfil do usuário, e se o perfil tem acesso ao método requisitado
	*
	* @return boolean
	*/
	public function restrict_access()
	{
		if( $this->logged_in() )
		{
			if( $this->_ignorePerms() ) {
				return true;
			} else {
				$url = $this->ci->router->fetch_class() .'.'. $this->ci->router->fetch_method();

				$userRoles = $this->getUserRole( $this->ci->session->userdata( 'logged_in' )['id_user'] );
				if( count( $userRoles ) <= 1 ) $userRoles = array( $userRoles );

        			foreach( $userRoles as $userRole ){
        				$rolePerms = $this->getRolePerms( $url, $userRole->id_role );
					if( $rolePerms ) return true;
        			}
			}
		}

		$this->ci->session->set_flashdata( 'restrict_access', 'Desculpe-nos, mas você não tem permissão à área que está tentando acessar.' );
		return false;
	}

	/**
	* Método privado de autenticação de usuário e senha para o login
	*
	* @param string $username
	* @param string $password
	* @return object | boolean false
	*/
	private function _authenticate( $username, $password )
	{
		$this->ci->db->where( array( 'activated' => 1, 'username' => $username ) );
		$usuario = $this->ci->db->get( 'user' )->row();

		if( $usuario && password_verify( $password, $usuario->password ) ) return $usuario;

		return false;
	}

	/**
	* Método privado de exceções de regras de ACL.
	* Utilizar annotation @ignoreACL
	*
	* @return boolean
	*/
	private function _ignorePerms()
	{
		$classe = new ReflectionClass( $this->ci->router->fetch_class() );
		$method = $classe->getMethod( $this->ci->router->fetch_method() );
		$doc = $method->getDocComment();
		preg_match_all( '#@(.*?)\n#s', $doc, $annotations );

		return ( in_array( 'ignoreACL', $annotations[1] ) ) ? true : false;
	}

	/**
	* Método privado de retorno de usuários x papéis
	*
	* @param int $id_user
	* @return object, array object
	*/
	private function getUserRole( $id_user )
	{
		$this->ci->db->select( "user.id_user, role_data.ID id_role, role_data.roleName" );
		$this->ci->db->join( 'user_roles', 'user.id_user = user_roles.userID' );
		$this->ci->db->join( 'role_data', 'role_data.ID = user_roles.roleID' );
		$this->ci->db->where( 'user.id_user', $id_user );

		$registros = $this->ci->db->get( 'user' );

		if( $registros ) {
			$registros = ( count( $registros->result() ) > 1 ) ? $registros->result() : $registros->row();
		}

		return $registros;
	}

	/**
	* Método privado de retorno de papéis x recursos
	*
	* @param string $url
	* @param int $id_role
	* @return object, array object
	*/
	private function getRolePerms( $url = false, $id_role = false )
	{
		$this->ci->db->select( "role_data.ID id_role, role_data.roleName, perm_data.*" );
		$this->ci->db->join( 'role_data', 'role_data.ID = role_perms.roleID' );
		$this->ci->db->join( 'perm_data', 'perm_data.ID = role_perms.permID' );
		if( $url ) $this->ci->db->where( 'perm_data.permKey', $url );
		if( $id_role ) $this->ci->db->where( 'role_data.ID', $id_role );

		$registros = $this->ci->db->get( 'role_perms' );

		if( $registros ) {
			$registros = ( count( $registros->result() ) > 1 ) ? $registros->result() : $registros->row();
		}

		return $registros;
	}

	/**
	* Método para retornar o ip real do usuário, excluindo o proxy
	* @return string | ip real
	*/
	public function get_ip_address()
	{
		$servers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);
		foreach( $servers  as $key ) {
			if( array_key_exists( $key, $_SERVER ) === true ) {
				$array_ips = explode( ',', $_SERVER[$key] );
				$ip = end( $array_ips );
				$ip = trim( $ip );

				if( $this->_validate_ip( $ip ) ) {
					return $ip;
				} else {
					return false;
				}
			}
		}
	}

	private function _validate_ip( $ip )
	{
		if( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 || FILTER_FLAG_IPV6 || FILTER_FLAG_NO_PRIV_RANGE || FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}

		return true;
	}
}