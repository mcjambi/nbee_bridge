<?php
global $city_data, $district_data, $ward_data;

$city_data = json_decode(file_get_contents(plugin_dir_path(__FILE__) . './diachinh/city_in_vietnam.json'), true);
$district_data = json_decode(file_get_contents(plugin_dir_path(__FILE__) . './diachinh/district_in_vietnam.json'), true);
$ward_data = json_decode(file_get_contents(plugin_dir_path(__FILE__) . './diachinh/ward_in_vietnam.json'), true);
