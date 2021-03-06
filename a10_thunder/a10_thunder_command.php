<?php
/*
 * Available global variables
 * $sms_csp pointer to csp context to send response to user
 * $sms_sd_ctx pointer to sd_ctx context to retreive usefull field(s)
 * $SMS_RETURN_BUF string buffer containing the result
 */
require_once 'smsd/sms_common.php';

require_once load_once('smsd', 'generic_command.php');
require_once load_once('smsd', 'cmd_create.php');
require_once load_once('smsd', 'cmd_read.php');
require_once load_once('smsd', 'cmd_update.php');
require_once load_once('smsd', 'cmd_delete.php');
require_once load_once('smsd', 'cmd_list.php');
require_once load_once('smsd', 'cmd_import.php');
// require_once load_once('a10_thunder', 'cmd_import.php');
require_once load_once('a10_thunder', 'adaptor.php');

class a10_thunder_command extends generic_command {
    var $parser_list;
    var $parsed_objects;
    var $create_list;
    var $delete_list;
    var $list_list;
    var $read_list;
    var $update_list;
    var $configuration;
    var $import_file_list;

    function __construct() {
        $this->parser_list = array();
        $this->create_list = array();
        $this->delete_list = array();
        $this->list_list = array();
        $this->read_list = array();
        $this->update_list = array();
        $this->import_file_list = array();
    }

    /*
     * #####################################################################################
     * IMPORT
     * #####################################################################################
     */

    /**
     * IMPORT configuration from router
     *
     * @param object $json_params
     *            parameters of the command
     * @param domElement $element
     *            DOM element of the definition of the command
     */
    function eval_IMPORT() {
        global $sms_sd_ctx;
        global $SMS_RETURN_BUF;

        try {
            $ret = sd_connect();
            if ($ret != SMS_OK) {
                return $ret;
            }
            if (! empty($this->parser_list)) {
                $objects = array();
                // One operation groups several parsers
                foreach ($this->parser_list as $operation => $parsers) {
                    $sub_list = array();
                    foreach ($parsers as $parser) {
                        $op_eval = $parser->eval_operation();
                        // Group parsers into evaluated operations
                        $sub_list["$op_eval"][] = $parser;
                    }

                    foreach ($sub_list as $op_eval => $sub_parsers) {
                        // Run evaluated operation
                        $running_conf = '';
                        $op_list = preg_split('@##@', $op_eval, 0, PREG_SPLIT_NO_EMPTY);
                        foreach ($op_list as $op) {
                            $running_conf .= sendexpectone(__FILE__ . ':' . __LINE__, $sms_sd_ctx, $op);
                        }
                        // Apply concerned parsers
                        foreach ($sub_parsers as $parser) {
                            $parser->parse($running_conf, $objects);
                        }
                    }
                }

                $this->parsed_objects = $objects;

                debug_object_conf($objects);
                $SMS_RETURN_BUF .= json_encode($objects);
            }

            sd_disconnect();
        } catch (Exception $e) {
            return $e->getCode();
        }

        return SMS_OK;
    }

    /*
     * #####################################################################################
     * IMPORT FROM FILE
     * #####################################################################################
     */
    /**
     * IMPORT configuration from router
     *
     * @param object $json_params
     *            parameters of the command
     * @param domElement $element
     *            DOM element of the definition of the command
     */
    function eval_IMPORTFROMFILE() {
        global $sms_sd_ctx;
        global $SMS_RETURN_BUF;

        if (! empty($this->parser_list)) {
            $objects = array();
            // One operation groups several parsers
            foreach ($this->parser_list as $operation => $parsers) {
                $sub_list = array();
                foreach ($parsers as $parser) {
                    $op_eval = $parser->eval_operation();
                    // Group parsers into evaluated operations
                    $sub_list["$op_eval"][] = $parser;
                }

                foreach ($sub_list as $op_eval => $sub_parsers) {
                    // Run evaluated operation
                    $running_conf = '';

                    foreach ($this->import_file_list as $import_file) {
                        echo "Reading file $import_file\n";
                        $running_conf .= file_get_contents($import_file);
                    }
                    // Apply concerned parsers
                    foreach ($sub_parsers as $parser) {
                        $parser->parse($running_conf, $objects);
                    }
                }
            }

            $this->parsed_objects = $objects;

            debug_object_conf($objects);
            $SMS_RETURN_BUF .= json_encode($objects);
        }

        return SMS_OK;
    }

    /**
     * save parsed objects to database
     */
    function apply_base_IMPORTFROMFILE($params) {
        global $sms_csp;
        global $sms_sd_info;

        if (empty($params)) {
            $ret = sms_bd_reset_conf_objects($sms_csp, $sms_sd_info);
            if ($ret !== SMS_OK) {
                return $ret;
            }
        }

        return set_conf_object_to_db($this->parsed_objects);
    }

    /*
     * #####################################################################################
     * CREATE
     * #####################################################################################
     */

    /**
     * Apply created object to device and if OK add object to the database.
     */
    function apply_device_CREATE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    /*
     * #####################################################################################
     * UPDATE
     * #####################################################################################
     */

    /**
     * Apply updated object to device and if OK add object to the database.
     */
    function apply_device_UPDATE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    /*
     * #####################################################################################
     * DELETE
     * #####################################################################################
     */
    function eval_DELETE() {
        global $SMS_RETURN_BUF;

        foreach ($this->delete_list as $delete) {
            $conf = $delete->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";

        return SMS_OK;
    }

    /**
     * Apply deleted object to device and if OK add object to the database.
     */
    function apply_device_DELETE($params) {
        debug_dump($this->configuration, "CONFIGURATION TO SEND TO THE DEVICE");

        $ret = sd_apply_conf($this->configuration, true);

        return $ret;
    }

    function eval_CREATE() {
        global $SMS_RETURN_BUF;

        foreach ($this->create_list as $create) {
            $conf = $create->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";
        return SMS_OK;
    }

    function eval_UPDATE() {
        global $SMS_RETURN_BUF;

        foreach ($this->update_list as $update) {
            $conf = $update->evaluate();
            $this->configuration .= $conf;
            $SMS_RETURN_BUF .= $conf;
        }
        $this->configuration .= "\n";
        $SMS_RETURN_BUF .= "\n";

        return SMS_OK;
    }
}

?>
