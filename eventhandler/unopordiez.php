<?php
require_once('ebtmessage.php');

class EBTMsgReceived extends EBTMessage
{
	public $validation_server = 'http://localhost/~erick/militantes/sms/validar.php';

	function __construct($filename)
	{
		parent::__construct($filename, EBTMSG_FILE);
	}

	function execute()
	{
		// Read the message
		$sClientNumber = $this->get_header('From');
		$sDateReceived = $this->get_header('Received');
		$sMsgIn = trim($this->get_message());
		$sMsgOut = "";

		$aCedulas = split(' ', $sMsgIn);
		var_dump($aCedulas);
		foreach ($aCedulas as $sCedula)
		{
			// Sanitize
			$sCedula = str_replace(',', '', $sCedula);
			$sCedula = str_replace('.', '', $sCedula);
			$sCedula =  trim($sCedula);
			
			echo "Cedula: " . $sCedula . "\n";

			// Si el mensaje esta en blanco es solo texto no hace nada
			if (empty($sCedula) or !is_numeric($sCedula))
			{
				continue;
			}

			// Si la cedula esta fuera de rango
			if(strlen($sCedula) < 6 or strlen($sCedula) > 8)
			{
				$sMsgOut = $sCedula . " No parece ser un numero de cedula.";
			}
			else
			{
				$param = array(
					"cedula" => $sCedula,
					"recibido" => $sDateReceived,
					"telf_auto" => $sClientNumber,
				);

				$data_string = json_encode($param);
				$ch = curl_init($this->validation_server);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
						'Content-Type: application/json',
						'Content-Length: ' . strlen($data_string)
					)
				);

				$result = curl_exec($ch);
				$jData = json_decode($result);
				
				var_dump($jData);

				if ($jData !== NULL)
				{
					if ($jData->existe)
					{
						// $sMsgOut = "Se ha registrado " . $jData->cedula . " con exito.";
						continue;
					}
					else
					{
						$sMsgOut = "La cedula " . $jData->cedula . " no está registrada.";
					}
				}
				else
				{
					$sMsgOut = "Ocurrio un error con " . $sCedula;
				}
			}

			// Send the automatic response
			$oResponse = new EBTMessage();
			$oResponse->set_header('To', $sClientNumber);
			$oResponse->set_message($sMsgOut);
			$oResponse->write();
		}
	}
}
