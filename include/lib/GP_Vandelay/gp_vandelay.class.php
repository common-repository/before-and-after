<?php
/* Importer/Exporter */
class GP_Vandelay
{
	var $filter_prefix = '';
	var $headers = array();
	var $export_path = '';
	var $export_filename = '';
	
	function __construct( $headers = array() ) {
		if (!empty($headers) ) {
			$this->set_headers($headers);
		}
	}
	
	function export($query_params_or_callback, $row_callback, $filename, $attachment = false)
	{		
		if( $attachment ) {
            // send response headers to the browser
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment;filename='.basename($filename) );
			header("Pragma: no-cache");
			header("Expires: 0");
            $fp = fopen('php://output', 'w');
        } else {
			// open csv file for writing
            $fp = fopen($filename, 'w');
        }	
		
		if ( !$fp ) {
			return false;
		}
		
		// output the headers if any were provided
		if ( $this->has_headers() ) {
			$header_row = $this->output_header_row();
			fputcsv($fp, $header_row);
		}
			
		// get a list of the rows to write
		$rows = $this->load_rows($query_params_or_callback);
		if ( !empty($rows) ) {
			// write each row to the file
			$row_index = 0; // use counter in case array keys are non-numeric
			foreach ($rows as $row) {
				// render this row using the provided callback
				$row_output = $this->output_row($row_callback, $row, $row_index);
				
				// write the output to the csv file
				fputcsv($fp, $row_output);
				
				$row_index++;
			}
		}
		
		// close the csv
		fclose($fp);
		
		// success!
		return true;
	}
	
	
	function output_header_row()
	{
		$headers = $this->has_headers()
				   ? $this->get_headers()
				   : array();

		$headers = apply_filters($this->filter_prefix . 'vandelay_header_row', $headers );		
		return $headers;
	}
	
	function load_rows($query_params_or_callback)
	{
		if ( is_callable($query_params_or_callback) ) {
			$rows = call_user_func($query_params_or_callback);
		}
		else if ( is_array($query_params_or_callback) ) {
			$rows = get_posts($query_params_or_callback);
		} else {
			throw new Exception('Invalid parameter passed to load_rows: must be a valid callback or an array.');
		}
		$rows = apply_filters($this->filter_prefix . 'vandelay_load_rows', $rows);
		return $rows;
	}

	function output_row($row_callback, $row, $row_index)
	{
		$output = '';
		if ( is_callable($row_callback) ) {
			$output = call_user_func($row_callback, $row, $row_index);
		}
		else {
			throw new Exception('Invalid parameter passed to output_row: must be a valid callback.');
		}
		$output = apply_filters($this->filter_prefix . 'vandelay_output_row', $output, $row, $row_index );
		return $output;
	}


	/* Has functions */
	
	function has_headers()
	{
		return ( !empty($this->headers) );
	}


	/* Getters and Setters */
	
	function get_headers()
	{
		return $this->headers;
	}

	function set_headers($headers)
	{
		$this->headers = $headers;
	}

	function get_filter_prefix()
	{
		return $this->filter_prefix;
	}

	function set_filter_prefix($filter_prefix)
	{
		$this->filter_prefix = $filter_prefix;
	}

	function get_export_path()
	{
		return $this->export_path;
	}

	function set_export_path($export_path)
	{
		$this->export_path = $export_path;
	}

	function get_export_filename()
	{
		return $this->export_filename;
	}

	function set_export_filename($export_filename)
	{
		$this->export_filename = $export_filename;
	}

}