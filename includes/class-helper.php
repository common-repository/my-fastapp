<?php

namespace MyFastApp;

class Helper
{
	/**
	  * Convert a rgba(r,g,b,a) string to ARGB hex string #AARRGGBB
	  * If a color is already in hex format, it return it untouched
	  * @param $color rgba( R, G , B, A) string
	  * @return string ARGB color in hex format
	  */
	public static function argb2hex($inputColor)
	{
		$color = trim($inputColor);

		if (strpos($color, "rgba") === false) return $inputColor;

		$regex = "#\((([^()]+|(?R))*)\)#";
		if (preg_match_all($regex, $color, $matches)) {
			$rgba = explode(",", implode(" ", $matches[1]));
		}
		else {
			$rgba = explode(",", $color);
		}

		$rr = self::decToHex($rgba["0"], 2);
		$gg = self::decToHex($rgba["1"], 2);
		$bb = self::decToHex($rgba["2"], 2);
		$aa = "";

		if (array_key_exists("3", $rgba)) {
			$aa = self::decToHex($rgba["3"] * 255, 2);
		}

		return strtoupper("#$aa$rr$gg$bb");
	}

	/**
	  * @param $input Decimal representation of a integer
	  * @param int $minimumDigitsNumber Minimun number of digits
	  * @return string Hex representation with the required number of digits
	  */
	private static function decToHex($input, $minimumDigitsNumber = 2)
	{
		return str_pad(dechex($input), $minimumDigitsNumber, "0", STR_PAD_LEFT);
	}

	public static function get_wp_site_url()
	{
		// Ottiene siteUrl completo del sito corrente
		if (function_exists('get_current_blog_id')) {
			$site_id = get_current_blog_id();
		}
		else {
			$site_id = 1;	// Default per installazioni singole
		}
		// Ottiene l'URL del sito specifico
		$site_url = get_site_url($site_id);
		return $site_url;
	}

	public static function get_wp_site_uid()
	{
		// Ottiene SiteUid del sito corrente
		$site_uid = self::get_wp_site_url();
		$site_uid = preg_replace('/https?:\/\//', '', $site_uid);
		$site_uid = rtrim($site_uid, '/');
		$site_uid = strtolower($site_uid);
		return $site_uid;
	}

	public static function get_wp_rest_url()
	{
		// Ottiene RestUrl del sito corrente
		$rest_url = /* rest_url() */ self::get_wp_site_url() . "/?rest_route=/";
		return $rest_url;
	}

	public static function get_az_rest_url()
	{
		return "https://myfastapp-api.azurewebsites.net/api/v1/";

		// Posso anche gestire il caso "stage", è sufficiente che stabiliamo una
		// installazione dedicata di WP riconoscibile da self::get_wp_site_url()
		$wp_site_url = strtolower(self::get_wp_site_url());
		if (str_contains($wp_site_url, "//localhost:")) {
		//	return "https://myfastapp-api-dev.azurewebsites.net/api/v1/";
			return "http://localhost:8181/api/v1/";
		} 
		else if (str_contains($wp_site_url, "//stage.teamonair.com")) {
			return "https://myfastapp-api-dev.azurewebsites.net/api/v1/";
		}
		else {
		//	return "https://myfastapp-api.azurewebsites.net/api/v1/";
			return "https://myfastapp-api-dev.azurewebsites.net/api/v1/";
		}
	}

	public static function send_problem_json($statusCode, $title, $detail, $type = "about:blank", $instance = null, $language = "en")
	{
		// Function to send an error response in RFC 7807 format with Content-Language header

		// Set the HTTP status code and the appropriate headers for problem+json response and content language
		http_response_code($statusCode);
		header('Content-Type: application/problem+json');
		header('Content-Language: ' . $language);

		// Create the problem response array based on RFC 7807 structure
		$problemResponse = array(
			"type"	=> $type,			// URI identifier for the type of error
			"title"	=> $title,			// Short human-readable summary of the problem
			"status" => $statusCode,	// HTTP status code
			"detail" => $detail			// Detailed explanation of the problem
		);

		// Include the instance field if provided
		if ($instance !== null) {
			$problemResponse['instance'] = $instance;
		}

		// Encode the response as JSON and output it
		echo json_encode($problemResponse);
		exit; // Stop further script execution
	}
}
