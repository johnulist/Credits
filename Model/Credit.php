<?php

App::uses('CreditsAppModel', 'Credits.Model');

class AppCredits extends CreditsAppModel {

	public $name = 'Credit';
	public $validate = array(
		'user_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
			),
		),
	);
	public $belongsTo = array(
		'User' => array(
			'className' => 'Users.User',
			'foreignKey' => 'user_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

/**
 * Updates User.credit_total by adding Credit.value to it.
 * Looks up the User with either Credit.user_id or Credit.email.
 *
 * @param array $data
 * @return boolean
 */
	public function add($data) {
		if (empty($data['Credit']['user_id']) && isset($data['Credit']['email'])) {
			$userCredit = $this->User->find('first', array(
				'conditions' => array(
					'User.email' => $data['Credit']['email'],
				),
				'fields' => array(
					'User.id',
					'User.credit_total'
				),
			));
		} else {
			$userCredit = $this->User->find('first', array(
				'conditions' => array('User.id' => $data['Credit']['user_id']),
				'fields' => array(
					'User.id',
					'User.credit_total'
				),
			));
		}

		$userCredit['User']['credit_total'] += $data['Credit']['value'];

		// we should not mess with other stuff, hence save only
		if ($this->User->save($userCredit, false)) {
			return true;
		} else {
			return false;
		}
	}

/**
 * Updates User.credit_total by adding Credit.quantity to it..
 * Looks up the User with either Credit.user_id or User.id.
 *
 * @param array $data
 * 				['Credit']['user_id']
 * 				['User']['id']
 * 				['Credit']['quantity'] (can be negative or positive number to credit total by)
 * @return boolean
 */
	public function changeUserCredits($data) {
		$userId = !empty($data['Credit']['user_id']) ? $data['Credit']['user_id'] : $data['User']['id'];
		$creditData = $this->User->find('first', array(
			'nocheck' => true,
			'fields' => array('User.credit_total', 'User.user_role_id'),
			'conditions' => array('User.id' => $userId)));
		$creditData['User']['credit_total'] = $creditData['User']['credit_total'] + $data['Credit']['quantity'];
		$creditData['User']['id'] = !empty($data['User']['id']) ? $data['User']['id'] : $data['Credit']['user_id'];
		$this->User->validate = false;
		if ($this->User->save($creditData)) {
			return true;
		} else {
			return false;
		}
	}

/**
 * Checks the incoming user for whether they have any credits available
 * @param int $userId
 * @param int $amount
 * @return mixed Returns the number of credits that would be left greater than or equal to, else false.
 */
	public function checkCredits($userId = null, $amount = 1) {
		$creditTotal = $this->User->field('credit_total', array('User.id' => $userId));
		if (($creditTotal - $amount) >= 0) {
			return $creditTotal;
		} else {
			return false;
		}
	}

/**
 * Get an array of Credit Types from the ENUM
 * @return array
 */
	public function creditTypes() {
		$creditTypes = array();
		foreach (Zuha::enum('CREDIT_TYPE') as $creditType) {
			$creditTypes[Inflector::underscore($creditType)] = $creditType;
		}
		return $creditTypes;
	}

/**
 * Map Transaction Item method
 * 
 * This trims an object, formats it's values if you need to, and returns the data to be merged with the Transaction data.
 * 
 * @param string $key
 * @return array The necessary fields to add a Transaction Item
	  public function mapTransactionItem($key) {
	  $itemData = $this->find('first', array('conditions' => array('id' => $key)));
	  $fieldsToCopyDirectly = array(
	  'name'
	  );
	  foreach($itemData['UserRole'] as $k => $v) {
	  if(in_array($k, $fieldsToCopyDirectly)) {
	  $return['TransactionItem'][$k] = $v;
	  }
	  }
	  return $return;
	  }
 */

/**
 * @param array $data A payment object
 */
	public function afterSuccessfulPayment($data) {
		foreach ($data['TransactionItem'] as $transactionItem) {
			if ($transactionItem['model'] == 'Credit') {
				$quantity = $this->field('amount', array('id' => $transactionItem['foreign_key']));
				// accomodate for multiple qty of transactionItem
				$quantity = $quantity * $transactionItem['quantity'];
				$this->changeUserCredits(array(
					'User' => array(
						'id' => $data['Customer']['id']
					),
					'Credit' => array(
						'quantity' => $quantity
					)
				));
			}
		}
	}

}

if (!isset($refuseInit)) {

	class Credit extends AppCredits {
		
	}

}
