<?php

class Swagger {

	protected $answer; // result.

	public function prepare_url()
	{
		$str = "https://wp.webspark.dev/wp-api/products";
		return $this->get_responce($str);
	}


	public function get_responce(string $url): string
	{
		$cURL = curl_init($url);
		curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1); // not echoed to browser.
		$bufRates = curl_exec($cURL); //execution.
		curl_close($cURL); // close.
		$this->answer = json_decode($bufRates, true);

		return (is_string($bufRates))? true : false ;
	}


	public function get_answer() {
		return $this->answer;
	}



}