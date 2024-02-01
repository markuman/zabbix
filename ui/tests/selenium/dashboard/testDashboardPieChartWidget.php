<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup widget
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends CWebTest {
	protected static $dashboardid;
	protected static $item_ids;
	const TYPE_ITEM_PATTERN = 'Item pattern';
	const TYPE_ITEM_LIST = 'Item list';
	const HOST_NAME_ITEM_LIST = 'Host for Pie charts';
	const HOST_NAME_SCREENSHOTS = 'Host for Pie chart screenshots';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Create the needed initial data in database and set static variables.
	 */
	public function prepareData() {
		// For faster tests set Pie chart as the default widget type.
		DB::delete('profiles', ['idx' => 'web.dashboard.last_widget_type', 'userid' => 1]);
		DB::insert('profiles',
			[
				[
					'profileid' => 99999,
					'userid' => 1,
					'idx' => 'web.dashboard.last_widget_type',
					'value_str' => 'piechart',
					'type' => 3
				]
			]
		);

		// Create a Dashboard for creating widgets.
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard',
			'auto_start' => 0,
			'pages' => [['name' => 'Pie chart test page']]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];

		// Create a host for Pie chart testing.
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME_ITEM_LIST,
				'groups' => [['groupid' => 6]], // Virtual machines.
				'items' => [
					[
						'name' => 'item-1',
						'key_' => 'key-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-2',
						'key_' => 'key-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => self::HOST_NAME_SCREENSHOTS,
				'groups' => [['groupid' => 6]], // Virtual machines.
				'items' => [
					[
						'name' => 'item-1',
						'key_' => 'item-1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-2',
						'key_' => 'item-2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'item-3',
						'key_' => 'item-3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					],
					[
						'name' => 'item-4',
						'key_' => 'item-4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT
					]
				]
			]
		]);
		self::$item_ids = $response['itemids'];
	}

	/**
	 * Test the elements and layout of the Pie chart create form.
	 */
	public function testDashboardPieChartWidget_Layout() {
		// Open the create form.
		$dashboard = $this->openDashboard();
		$form = $dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->one();
		$this->assertEquals('Add widget', $dialog->getTitle());

		// Check modal Help and Close buttons.
		foreach (['xpath:.//*[@title="Help"]', 'xpath:.//button[@title="Close"]'] as $selector) {
			$this->assertTrue($dialog->query($selector)->one()->isClickable());
		}

		// Assert that the generic widget Type field works as expected.
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);
		$this->assertFalse($form->query('id:data_set')->exists());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Pie chart')]);
		$this->assertTrue($form->query('id:data_set')->exists());

		// Check other generic widget fields.
		$expected_values = [
			'Type' => 'Pie chart',
			'Show header' => true,
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)'
		];
		$form->checkValue($expected_values);
		$this->assertFieldAttributes($form, 'Name', ['placeholder' => 'default', 'maxlength' => 255]);
		$this->assertEquals(array_keys($expected_values), $form->getLabels(CElementFilter::CLICKABLE)->asText());

		foreach (array_keys($expected_values) as $field) {
			$this->assertTrue($form->getField($field)->isEnabled());
		}

		// Check tabs.
		$this->assertEquals(['Data set', 'Displaying options', 'Time period', 'Legend'], $form->getTabs());

		// Check Data set - Item pattern.
		$expected_values = [
			'xpath:.//input[@id="ds_0_color"]/..' => 'FF465C', // data set color
			'xpath:.//div[@id="ds_0_hosts_"]/..' => '', // host pattern
			'xpath:.//div[@id="ds_0_items_"]/..' => '', // item pattern
			'Aggregation function' => 'last',
			'Data set aggregation' => 'not used',
			'Data set label' => ''
		];
		$form->checkValue($expected_values);
		$expected_labels = ['Data set #1', 'Aggregation function', 'Data set aggregation', 'Data set label'];
		$this->assertAllVisibleLabels($form->query('id:data_set')->one(), $expected_labels);
		$this->validateDataSetHintboxes($form);

		$buttons = [
			'id:ds_0_hosts_', // host multiselect
			'id:ds_0_items_', // item multiselect
			'xpath:.//li[@data-set="0"]//button[@title="Delete"]', // first data set delete icon
			'id:dataset-add', // button "Add new data set"
			'id:dataset-menu' // context menu of button "Add new data set"
		];
		foreach ($buttons as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		$options = [
			'Aggregation function' => ['last', 'min', 'max', 'avg', 'count', 'sum', 'first'],
			'Data set aggregation' => ['not used', 'min', 'max', 'avg', 'count', 'sum']
		];
		foreach ($options as $dropdown => $expected_options) {
			$this->assertEquals($expected_options, $form->getField($dropdown)->getOptions()->asText());
		}

		foreach (['id:ds_0_hosts_' => 'host pattern','id:ds_0_items_' => 'item pattern'] as $selector => $placeholder) {
			$this->assertFieldAttributes($form, $selector, ['placeholder' => $placeholder], true);
		}

		$this->assertFieldAttributes($form, 'Data set label', ['placeholder' => 'Data set #1', 'maxlength' => 255]);

		// Check Data set - Item list.
		$this->addNewDataSet($form, self::TYPE_ITEM_LIST);
		$form->invalidate();
		$expected_values = [
			'Aggregation function' => 'last',
			'Data set aggregation' => 'not used',
			'Data set label' => ''
		];
		$form->checkValue($expected_values);
		$expected_labels = ['Data set #1', 'Data set #2', 'Aggregation function', 'Data set aggregation', 'Data set label'];
		$this->assertAllVisibleLabels($form->query('id:data_set')->one(), $expected_labels);
		$this->validateDataSetHintboxes($form);

		$buttons = [
			'xpath:.//li[@data-set="1"]//button[@title="Delete"]', // second data set delete icon
			'id:dataset-add', // button "Add new data set"
			'id:dataset-menu' // context menu of button "Add new data set"
		];
		foreach ($buttons as $selector) {
			$this->assertTrue($form->query($selector)->one()->isClickable());
		}

		foreach ($options as $dropdown => $expected_options) {
			$this->assertEquals($expected_options, $form->getField($dropdown)->getOptions()->asText());
		}

		$this->assertFieldAttributes($form, 'Data set label', ['placeholder' => 'Data set #2', 'maxlength' => 255]);

		// Displaying options tab.
		$form->selectTab('Displaying options');
		$this->query('id:displaying_options')->one()->waitUntilVisible();
		$form->invalidate();
		$expected_values = [
			'History data selection' => 'Auto',
			'Draw' => 'Pie',
			'Space between sectors' => '1',
			'id:merge' => false, // "Merge sectors smaller than" checkbox
			'id:merge_percent' => '1', // "Merge sectors smaller than" input
			'id:merge_color' => 'B0AF07' // "Merge sectors smaller than" color picker
		];
		$form->checkValue($expected_values);
		$expected_labels = ['History data selection', 'Draw', 'Space between sectors', 'Merge sectors smaller than'];
		$this->assertAllVisibleLabels($form->query('id:displaying_options')->one(), $expected_labels);

		$radios = ['History data selection' => ['Auto', 'History', 'Trends'], 'Draw' => ['Pie', 'Doughnut']];
		foreach ($radios as $radio => $labels) {
			$radio_element = $form->getField($radio);
			$radio_element->isEnabled();
			$this->assertEquals($labels, $radio_element->getLabels()->asText());
		}

		$this->assertRangeSliderParameters($form, 'Space between sectors', ['min' => '0', 'max' => '10', 'step' => '1']);

		foreach (['merge' => true, 'merge_percent' => false, 'merge_color' => false] as $id => $enabled) {
			$this->assertTrue($form->query('id', $id)->one()->isEnabled($enabled));
		}

		$form->fill(['id:merge' => true]);
		$form->invalidate();

		foreach (['merge_percent', 'merge_color'] as $id) {
			$this->assertTrue($form->query('id', $id)->one()->isEnabled());
		}

		$form->fill(['Draw' => 'Doughnut']);
		$this->query('id:show_total_fields')->one()->waitUntilVisible();
		$form->invalidate();
		$inputs_enabled = [
			'Width' => true,
			'Show total value' => true,
			'Size' => false,
			'Decimal places' => false,
			'Units' => false,
			'Bold' => false,
			'Colour' => false
		];
		$expected_labels = array_merge($expected_labels, array_keys($inputs_enabled));
		$this->assertAllVisibleLabels($form->query('id:displaying_options')->one(), $expected_labels);
		$this->assertRangeSliderParameters($form, 'Width', ['min' => '20', 'max' => '50', 'step' => '10']);
		$form->checkValue(['Space between sectors' => 1, 'Width' => 50]);

		foreach($inputs_enabled as $label => $enabled) {
			$this->assertEquals($enabled, $form->getField($label)->isEnabled());
		}

		$field_maxlengths = [
			'id:space' => 2,
			'id:merge_percent' => 2,
			'id:width' => 2,
			'Decimal places' => 1,
			'id:units' => 2048
		];
		foreach ($field_maxlengths as $field_selector => $maxlength) {
			$this->assertFieldAttributes($form, $field_selector, ['maxlength' => $maxlength]);
		}

		$form->fill(['Show total value' => true]);
		$inputs_enabled = [
			'Size' => true,
			'Decimal places' => true,
			'Units' => false,
			'Bold' => true,
			'Colour' => true
		];
		foreach($inputs_enabled as $label => $enabled) {
			$this->assertEquals($enabled, $form->getField($label)->isEnabled());
		}

		$form->fill(['Size' => 'Custom']);
		$value_size = $form->getField('id:value_size_custom_input');
		$this->assertTrue($value_size->isVisible() && $value_size->isEnabled(), 'Input');
		$this->assertEquals('20', $value_size->getValue());

		$form->fill(['id:units_show' => true]);
		$this->assertTrue($form->getField('Units')->isEnabled());

		// Time period tab.
		$form->selectTab('Time period');
		$this->query('id:time_period')->one()->waitUntilVisible();
		$form->invalidate();

		$expected_labels = ['History data selection', 'Draw', 'Space between sectors', 'Merge sectors smaller than'];
		$this->assertAllVisibleLabels($form->query('id:displaying_options')->one(), $expected_labels);

		$time_period = $form->getField('Time period');
		$this->assertTrue($time_period->isEnabled());
		$this->assertEquals(['Dashboard', 'Widget', 'Custom'], $time_period->getLabels()->asText());

		$form->fill(['Time period' => 'Widget']);
		$form->checkValue(['Widget' => '']);
		$widget_field = $form->getField('Widget');
		$this->assertTrue($widget_field->isVisible(), $widget_field->isEnabled(), $form->isRequired('Widget'),
				'Widget field is not interactable or is not required'
		);
		$this->assertFieldAttributes($form, 'Widget', ['placeholder' => 'type here to search'], true);
		$widget_field->query('button:Select')->waitUntilClickable()->one()->click();
		$widget_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals('Widget', $widget_dialog->getTitle());
		$widget_dialog->close();

		$form->fill(['Time period' => 'Custom']);
		$form->checkValue(['From' => 'now-1h', 'To' => 'now']);

		foreach (['From', 'To'] as $label) {
			$field = $form->getField($label);
			$this->assertTrue($field->isVisible());
			$this->assertTrue($field->isEnabled());
			$this->assertTrue($form->isRequired($label));
			$this->assertTrue($field->query('id', 'time_period_'.strtolower($label).'_calendar')->one()->isClickable());
			$this->assertFieldAttributes($form, $label, ['placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255], true);
		}

		// Legend tab.
		$form->selectTab('Legend');
		$this->query('id:legend_tab')->one()->waitUntilVisible();
		$form->invalidate();

		$form->checkValue(['Show legend' => true, 'Show aggregation function' => false, 'Number of rows' => 1, 'Number of columns' => 4]);

		$this->assertRangeSliderParameters($form, 'Number of rows', ['min' => '1', 'max' => '10', 'step' => '1']);
		$this->assertRangeSliderParameters($form, 'Number of columns', ['min' => '1', 'max' => '4', 'step' => '1']);

		$form->fill(['Show legend' => false]);

		foreach (['Show aggregation function', 'Number of rows', 'Number of columns'] as $label) {
			$field = $form->getField($label);
			$this->assertFalse($field->isEnabled());
			$this->assertTrue($field->isVisible());
		}

		// Footer buttons.
		$this->assertEquals(['Add', 'Cancel'],
			$dialog->getFooter()->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText()
		);
	}

	public function getPieChartData() {
		return [
			// Mandatory fields only.
			[
				[
					'fields' => []
				]
			],
			// Mandatory fields only - Data set type Item list.
			[
				[
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST,
							'host' => self::HOST_NAME_ITEM_LIST,
							'items' => [
								['name' =>'item-1']
							]
						]
					]
				]
			],
			// Largest number of fields possible. Data set aggregation has to be 'none' because of Total type item.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => 'Test all possible fields',
							'Show header' => false,
							'Refresh interval' => '30 seconds'
						],
						'Data set' => [
							[
								'host' => ['Host*', 'one', 'two'],
								'item' => ['Item*', 'one', 'two'],
								'color' => '00BCD4',
								'Aggregation function' => 'min',
								'Data set aggregation' => 'not used',
								'Data set label' => 'Label 1'
							],
							[
								'type' => self::TYPE_ITEM_LIST,
								'host' => self::HOST_NAME_ITEM_LIST,
								'Aggregation function' => 'max',
								'Data set aggregation' => 'not used',
								'Data set label' => 'Label 2',
								'items' => [
									[
										'name' => 'item-1',
										'il_color' => '000000',
										'il_type' => 'Total'
									],
									[
										'name' => 'item-2'
									]
								]
							]
						],
						'Displaying options' => [
							'History data selection' => 'History',
							'Draw' => 'Doughnut',
							'Width' => '40',
							'Space between sectors' => '2',
							'id:merge' => true,
							'id:merge_percent' => '10',
							'xpath:.//input[@id="merge_color"]/..' => 'EEFF22',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => '25',
							'Decimal places' => '1',
							'id:units_show' => true,
							'id:units' => 'GG',
							'Bold' => true,
							'Colour' => '4FC3F7'
						],
						'Time period' => [
							'Time period' => 'Custom',
							'From' => 'now-3h',
							'To' => 'now-2h'
						],
						'Legend' => [
							'Show legend' => true,
							'Show aggregation function' => true,
							'Number of rows' => 2,
							'Number of columns' => 3
						]
					]
				]
			],
			// Several data sets.
			[
				[
					'fields' => [
						'Data set' => [[], [], [], [], []]
					]
				]
			],
			// Unicode values.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Data set' => [
							'host' => '&nbsp; <script>alert("host");</script>',
							'item' => '&nbsp; <script>alert("item");</script>',
							'Data set label' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:units_show' => true,
							'id:units' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						]
					]
				]
			],
			// Different Time period formats.
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '2020',
							'To' => '2020-10-10 00:00:00'
						]
					]
				]
			],
			// Missing Data set.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'delete_data_set' => true
					],
					'error' => 'Invalid parameter "Data set": cannot be empty.'
				]
			],
			// Missing Host pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['item' => '*']
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['host' => '*']
					],
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Missing Host AND Item pattern.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'remake_data_set' => true,
						'Data set' => ['item' => '', 'host' => '']
					],
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item list.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST
						]
					],
					'error' => 'Invalid parameter "Data set/1/itemids": cannot be empty.'
				]
			],
			// Merge sector % value too small.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '0'
						]
					],
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Merge sector % value too big.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '11'
						]
					],
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Total value custom size missing.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => ''
						]
					],
					'error' => 'Invalid parameter "value_size": value must be one of 1-100.'
				]
			],
			// Total value custom size out of range.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => '101'
						]
					],
					'error' => 'Invalid parameter "value_size": value must be one of 1-100.'
				]
			],
			// Decimal places value too big.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Decimal places' => '7'
						]
					],
					'error' => 'Invalid parameter "Decimal places": value must be one of 0-6.'
				]
			],
			// Empty Time period (Widget).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Widget'
						]
					],
					'error' => 'Invalid parameter "Time period/Widget": cannot be empty.'
				]
			],
			// Empty Time period (Custom).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '',
							'To' => ''
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// Invalid Time period (Custom).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '0',
							'To' => '2020-13-32'
						]
					],
					'error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// Bad color values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Data set' => [
							'host' => 'Host*',
							'item' => 'Item*',
							'color' => 'FFFFFG'
						],
						'Displaying options' => [
							'id:merge' => true,
							'xpath:.//input[@id="merge_color"]/..' => 'FFFFFG',
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Colour' => 'FFFFFG'
						]
					],
					'error' => [
						'Invalid parameter "Data set/1/color": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "merge_color": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "Colour": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			]
		];
	}

	/**
	 * Test creation of Pie chart.
	 *
	 * @dataProvider getPieChartData
	 */
	public function testDashboardPieChartWidget_Create($data){
		$this->createUpdatePieChart($data);
	}

	/**
	 * Test updating of Pie chart.
	 *
	 * @dataProvider getPieChartData
	 */
	public function testDashboardPieChartWidget_Update($data){
		$this->createUpdatePieChart($data, 'Edit widget');
	}

	public function getSimpleUpdateAndCanceData() {
		return [
			// Simple update.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel update widget.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'update' => true,
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			]
		];
	}

	/**
	 * Test simple update, cancel update and cancel create scenarios.
	 *
	 * @dataProvider getSimpleUpdateAndCanceData
	 *
	 * @param $data
	 * @return void
	 */
	public  function testDashboardPieChartWidget_SimpleUpdateAndCancel($data) {
		$update = CTestArrayHelper::get($data, 'update', false);
		$widget_name = md5(serialize($data));

		if ($update) {
			$this->createCleanWidget($widget_name);
		}

		// Get a hash for DB data comparison.
		$hash_sql = 'SELECT * FROM widget w INNER JOIN widget_field wf ON w.widgetid=wf.widgetid ORDER BY w.widgetid';
		$old_hash = CDBHelper::getHash($hash_sql);

		$dashboard = $this->openDashboard();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->edit()->getWidget($widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		// Fill mandatory fields in the create scenario.
		if (!$update) {
			$this->fillForm([], $form);
		}

		// Save the widget or cancel.
		if (CTestArrayHelper::get($data, 'save_widget')) {
			$form->submit();
		}
		else {
			COverlayDialogElement::find()->one()->query('button:Cancel')->one()->click();
		}

		// Save the dashboard if needed.
		if (CTestArrayHelper::get($data, 'save_dashboard')) {
			$dashboard->save();
		}

		// Assert that the count of widgets and DB hash has not changed.
		$dashboard = $this->openDashboard(false);
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		$this->assertEquals($old_hash, CDBHelper::getHash($hash_sql));
	}

	/**
	 * Test deleting a Pie chart widget.
	 */
	public function testDashboardPieChartWidget_Delete(){
		$name = 'Pie chart for deletion';
		$this->createCleanWidget($name);
		$widget_id = CDBHelper::getValue('SELECT widgetid FROM widget WHERE name='.zbx_dbstr($name));

		// Delete the widget and save dashboard.
		$dashboard = $this->openDashboard();
		$old_widget_count = $dashboard->getWidgets()->count();
		$dashboard->edit()->deleteWidget($name)->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Assert that the widget has been deleted.
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());

		// Check both - widget and widget_field DB tables.
		$sql = 'SELECT NULL FROM widget w CROSS JOIN widget_field wf'.
				' WHERE w.widgetid='.zbx_dbstr($widget_id).' OR wf.widgetid='.zbx_dbstr($widget_id);
		$this->assertEquals(0, CDBHelper::getCount($sql));

		// Check by name too.
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM widget WHERE name='.zbx_dbstr($name)));
	}

	public function getPieChartDisplayData() {
		return [
			// No data.
			[
				[
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1']
					],
					'item_data' => [],
					'screenshot_id' => 'no_data'
				]
			],
			// One item, custom data set name.
			[
				[
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-1'],
						['name' => 'ds.0.data_set_label', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'TEST SET ☺'],
						['name' => 'ds.0.dataset_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => [
							['value' => 99]
						]
					],
					'expected_dataset_name' => 'TEST SET ☺',
					'expected_sectors' => [
						'item-1' => ['value' => '99', 'color' => 'rgb(255, 70, 92)']
					],
					'screenshot_id' => 'one_item'
				]
			],
			// Two items, data set aggregate function, very small item values.
			[
				[
					'widget_name' => '2 items',
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => self::HOST_NAME_SCREENSHOTS],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-3'],
						['name' => 'ds.0.items.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-4'],
						['name' => 'ds.0.aggregate_function', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'legend_aggregation', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1]
					],
					'item_data' => [
						'item-3' => [
							['value' => 0.00000000000000004]
						],
						'item-4' => [
							['value' => 0.00000000000000001]
						]
					],
					'expected_legend_function' => 'max',
					'expected_sectors' => [
						'item-3' => ['value' => '4E-17', 'color' => 'rgb(255, 70, 92)'],
						'item-4' => ['value' => '1E-17', 'color' => 'rgb(255, 197, 219)']
					],
					'screenshot_id' => 'two_items'
				]
			],
			// Four items, host and item pattern, mixed value types, custom color, hide legend and header.
			[
				[
					'view_mode' => 1,
					'widget_fields' => [
						['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Host for Pie chart screen*'],
						['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'item-*'],
						['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FFA726'],
						['name' => 'legend', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0]
					],
					'item_data' => [
						'item-1' => [
							['value' => 1]
						],
						'item-2' => [
							['value' => 2]
						],
						'item-3' => [
							['value' => 3.0]
						],
						'item-4' => [
							['value' => 4.4]
						]
					],
					'expected_sectors' => [
						'item-1' => ['value' => '1', 'color' => 'rgb(191, 103, 0)'],
						'item-2' => ['value' => '2', 'color' => 'rgb(255, 167, 38)'],
						'item-3' => ['value' => '3', 'color' => 'rgb(255, 230, 101)'],
						'item-4' => ['value' => '4.4', 'color' => 'rgb(255, 255, 165)']
					],
					'screenshot_id' => 'four_items'
				]
			]/*,
			TODO: uncomment after DEV-2666 is merged.
			// Data set type Item list, Total item, merging enabled, doughnut with total value, custom legend display.
			[
				[
					'widget_fields' => [
						// Items and their colors.
						['name' => 'ds.0.dataset_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.itemids.0', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-1'],
						['name' => 'ds.0.type.0', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'ds.0.color.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FFEBEE'],
						['name' => 'ds.0.itemids.1', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-2'],
						['name' => 'ds.0.type.1', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.1', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'E53935'],
						['name' => 'ds.0.itemids.2', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-3'],
						['name' => 'ds.0.type.2', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.2', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '546E7A'],
						['name' => 'ds.0.itemids.3', 'type' => ZBX_WIDGET_FIELD_TYPE_ITEM, 'value' => 'item-4'],
						['name' => 'ds.0.type.3', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'ds.0.color.3', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '546EAA'],
						// Drawing and total value options.
						['name' => 'draw_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'width', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 30],
						['name' => 'total_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size_type', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_size', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 30],
						['name' => 'decimal_places', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 0],
						['name' => 'units_show', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'units', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '♥'],
						['name' => 'value_bold', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'value_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => '7CB342'],
						['name' => 'space', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'merge', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 1],
						['name' => 'merge_percent', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 10],
						['name' => 'merge_color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'B71C1C'],
						['name' => 'legend_lines', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2],
						['name' => 'legend_columns', 'type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'value' => 2]
					],
					'item_data' => [
						'item-1' => [
							['value' => 100]
						],
						'item-2' => [
							['value' => 82]
						],
						'item-3' => [
							['value' => 4]
						],
						'item-4' => [
							['value' => 5]
						]
					],
					'expected_total' => '100 ♥',
					'expected_sectors' => [
						'item-1' => ['value' => '100 ♥', 'color' => 'rgb(255, 235, 238)'],
						'item-2' => ['value' => '82 ♥', 'color' => 'rgb(229, 57, 53)'],
						'Other' => ['value' => '9 ♥', 'color' => 'rgb(183, 28, 28)']
					],
					'screenshot_id' => 'doughnut'
				]
			]*/
		];
	}

	/**
	 * Generate different Pie charts and assert the display.
	 *
	 * @dataProvider getPieChartDisplayData
	 */
	public function testDashboardPieChartWidget_PieChartDisplay($data) {
		// Delete item history in DB.
		foreach ([1, 2, 3, 4] as $id) {
			CDataHelper::removeItemData(self::$item_ids[self::HOST_NAME_SCREENSHOTS.':item-'.$id]);
		}

		// Set new item history.
		foreach($data['item_data'] as $item_key => $item_data) {
			// One item may have more than one history record.
			foreach ($item_data as $record) {
				// Minus 10 seconds for safety.
				$time = time() - 10 + CTestArrayHelper::get($record, 'time');
				CDataHelper::addItemData(self::$item_ids[self::HOST_NAME_SCREENSHOTS.':'.$item_key], $record['value'], $time);
			}
		}

		// Fill in Item ids (this only applies to Item list data sets).
		foreach ($data['widget_fields'] as $id => $field) {
			if (preg_match('/^ds\.[0-9]\.itemids\.[0-9]$/', $field['name'])) {
				$field['value'] = self::$item_ids[self::HOST_NAME_SCREENSHOTS.':'.$field['value']];
				$data['widget_fields'][$id] = $field;
			}
		}

		// Create the Pie chart.
		CDataHelper::call('dashboard.update',
			[
				'dashboardid' => self::$dashboardid,
				'pages' => [
					[
						'widgets' => [
							[
								'name' => CTestArrayHelper::get($data, 'widget_name', ''),
								'view_mode' => CTestArrayHelper::get($data, 'view_mode', 0),
								'type' => 'piechart',
								'width' => 12,
								'height' => 8,
								'fields' => $data['widget_fields']
							]
						]
					]
				]
			]
		);

		$dashboard = $this->openDashboard();
		$widget = $dashboard->getWidgets()->first();

		// Only look sectors up if checking sectors.
		if (CTestArrayHelper::get($data, 'expected_sectors')) {
			$sectors = $widget->query('class:svg-pie-chart-arc')->waitUntilVisible()->all()->asArray();
		}

		// Assert Pie chart sectors by inspecting 'data-hintbox-contents' attribute.
		foreach (CTestArrayHelper::get($data, 'expected_sectors', []) as $item_name => $expected_sector) {
			// The name shown in the legend and in the hintbox.
			$legend_name = self::HOST_NAME_SCREENSHOTS.': '.$item_name;

			// Special case - legend name includes aggregation function.
			if (CTestArrayHelper::get($data, 'expected_legend_function')) {
				$legend_name = CTestArrayHelper::get($data, 'expected_legend_function').'('.$legend_name.')';
			}

			// Special case - custom legend name.
			if (CTestArrayHelper::get($data, 'expected_dataset_name')) {
				$legend_name = $data['expected_dataset_name'];
			}

			// Special case - legend name is "Other" because sectors were merged.
			if ($item_name === 'Other') {
				$legend_name = 'Other: ';
			}

			// Check if any of the sectors matches the expected sector.
			foreach ($sectors as $sector) {
				$hintbox_html = $sector->getAttribute('data-hintbox-contents');

				// If 'data-hintbox-contents' attribute matches the sector.
				if (strpos($hintbox_html, $legend_name)) {
					// Assert hintbox value.
					$matches = [];

					// Assert sector fill color.
					preg_match('/fill: (.*?);/', $sector->getAttribute('style'), $matches);
					$this->assertEquals($expected_sector['color'], $matches[1]);

					// Open the hintbox and assert the value and legend color.
					$sector->click();
					$hintbox = $this->query('class:overlay-dialogue')->asOverlayDialog()->waitUntilReady()->all()->last();
					$this->assertEquals($expected_sector['value'],
							$hintbox->query('class:svg-pie-chart-hintbox-value')->one()->getText()
					);
					$this->assertEquals('background-color: '.$expected_sector['color'].';',
							$hintbox->query('class:svg-pie-chart-hintbox-color')->one()->getAttribute('style')
					);
					$hintbox->close();

					// Assertion successful, continue to the next expected sector.
					continue 2;
				}
			}

			// Fail test if no match found.
			$this->fail('Expected sector for '.$item_name.' not found.');
		}

		// Assert expected Total value.
		if (CTestArrayHelper::get($data, 'expected_total')) {
			$this->assertEquals($data['expected_total'], $widget->query('class:svg-pie-chart-total-value')->one()->getText());
		}

		// Make sure none of the sectors are hovered.
		$this->query('id:page-title-general')->one()->hoverMouse();

		// Wait for the sector animation to end before taking a screenshot.
		sleep(1);

		// Screenshot the widget.
		$this->page->removeFocus();
		$this->assertScreenshot($widget, 'piechart_display_'.$data['screenshot_id']);
	}

	/**
	 * Creates or updates a widget according to data from data provider.
	 *
	 * @param array  $data                data from data provider
	 * @param string $edit_widget_name    if this is set, then a widget named like this is updated
	 * @param bool   $simple_update       true will perform a simple update
	 * @param bool   $cancel_update       true will cancel the update
	 */
	protected function createUpdatePieChart($data, $edit_widget_name = null) {
		if ($edit_widget_name) {
			$this->createCleanWidget($edit_widget_name);
		}

		$dashboard = $this->openDashboard();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $edit_widget_name
			? $dashboard->edit()->getWidget($edit_widget_name)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		$this->fillForm($data['fields'], $form);
		$form->submit();

		// Assert the result.
		$this->assertEditFormAfterSave($data, $dashboard);

		// Check total Widget count.
		$count_added = !$edit_widget_name && CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD ? 1 : 0;
		$this->assertEquals($old_widget_count + $count_added, $dashboard->getWidgets()->count());
	}

	/**
	 * Checks the hintboxes in the Create/Edit form for both Data set forms.
	 *
	 * @param CFormElement $form    data set form
	 */
	protected function validateDataSetHintboxes($form) {
		$hints = [
			'Aggregation function' => 'Aggregates each item in the data set.',
			'Data set aggregation' => 'Aggregates the whole data set.',
			'Data set label' => 'Also used as legend label for aggregated data sets.'
		];

		foreach ($hints as $field => $text) {
			// Summon the hint-box, assert text and close.
			$form->getLabel($field)->query('xpath:./button[@data-hintbox]')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->asOverlayDialog()->waitUntilPresent()->one();
			$this->assertEquals($text, $hint->getText());
			$hint->query('xpath:./button')->one()->click();
		}
	}

	/**
	 * Asserts that a range/slider input is displayed as expected.
	 *
	 * @param string       $label              label of the range input
	 * @param string       $input_id           id for the input field right next to the slider
	 * @param CFormElement $form               parent form
	 * @param array        $expected_values    the attribute values expected
	 */
	protected function assertRangeSliderParameters($form, $label, $expected_values) {
		$range = $form->getField($label)->query('xpath:.//input[@type="range"]')->one();
		foreach ($expected_values as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $range->getAttribute($attribute));
		}
	}

	/**
	 * Resets the dashboard and creates a single Pie chart widget.
	 *
	 * @param string $widget_name    name of the widget to be created
	 */
	protected function createCleanWidget($widget_name){
		CDataHelper::call('dashboard.update', [
			'dashboardid' => self::$dashboardid,
			'pages' => [
				[
					'widgets' => [
						[
							'name' => $widget_name,
							'type' => 'piechart',
							'width' => 8,
							'height' => 4,
							'fields' => [
								['name' => 'ds.0.hosts.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Host'],
								['name' => 'ds.0.items.0', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'Test Items'],
								['name' => 'ds.0.color', 'type' => ZBX_WIDGET_FIELD_TYPE_STR, 'value' => 'FF465C']
							]
						]
					]
				]
			]
		]);
	}

	/**
	 * Asserts that the data is saved and displayed as expected in the Edit form.
	 *
	 * @param array             $data         data from data provider
	 * @param CDashboardElement $dashboard    dashboard element
	 */
	protected function assertEditFormAfterSave($data, $dashboard) {
		$widget_name = CTestArrayHelper::get($data['fields'], 'main_fields.Name', md5(serialize($data['fields'])));
		$count_sql = 'SELECT NULL FROM widget WHERE name='.zbx_dbstr($widget_name);


		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			COverlayDialogElement::ensureNotPresent();

			// Save Dashboard.
			$widget = $dashboard->getWidget($widget_name);
			$widget->query('xpath:.//div[contains(@class, "is-loading")]')->waitUntilNotPresent();
			$widget->getContent()->query('class:svg-pie-chart')->waitUntilVisible();
			$dashboard->save();

			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Assert data in edit form.
			$form = $widget->edit();

			// Check main fields
			$main_fields = CTestArrayHelper::get($data['fields'], 'main_fields', []);
			$main_fields['Name'] = $widget_name;
			$form->checkValue($main_fields);

			$data_sets = $this->extractDataSets($data['fields']);
			$last = count($data_sets) - 1;

			// Check Data set tab.
			foreach ($data_sets as $i => $data_set) {
				$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
				unset($data_set['type']);

				// Additional steps for Item list.
				if ($type === self::TYPE_ITEM_LIST) {
					// Check Host and all Items.
					foreach ($data_set['items'] as $item) {
						$this->assertTrue($form->query('link', $data_set['host'].': '.$item['name'])->exists());
					}

					unset($data_set['host']);
				}
				$data_set = $this->remapDataSet($data_set, $i);
				$form->checkValue($data_set);

				// Check data set label.
				$label = CTestArrayHelper::get($data_set, 'Data set label', 'Data set #'.$i + 1);
				$this->assertEquals($label,
					$form->query('xpath:.//li[@data-set="'.$i.'"]//label[@class="sortable-drag-handle js-dataset-label"]')->one()->getText()
				);

				// Open the next data set, if it exists.
				if ($i !== $last) {
					$form->query('xpath:.//li[contains(@class, "list-accordion-item")]['.
							($i + 2).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
					$form->invalidate();
				}
			}

			// Check values in other tabs
			$tabs = ['Displaying options', 'Time period', 'Legend'];
			foreach ($tabs as $tab) {
				if (array_key_exists($tab, $data['fields'])) {
					$form->selectTab($tab);
					$form->checkValue($data['fields'][$tab]);
				}
			}

			// Assert DB record exists.
			$this->assertEquals(1, CDBHelper::getCount($count_sql));
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Assert DB record does not exist.
			$this->assertEquals(0, CDBHelper::getCount($count_sql));
		}

	}

	/**
	 * Fill Pie chart widget edit form with provided data.
	 *
	 * @param array        $fields    field data to fill
	 * @param CFormElement $form      form to be filled
	 */
	protected function fillForm($fields, $form) {
		// Fill main fields.
		$main_fields = CTestArrayHelper::get($fields, 'main_fields', []);
		$main_fields['Name'] = CTestArrayHelper::get($fields, 'main_fields.Name', md5(serialize($fields)));
		$form->fill($main_fields);

		// Fill datasets.
		$delete = CTestArrayHelper::get($fields, 'delete_data_set');
		$remake = CTestArrayHelper::get($fields, 'remake_data_set');

		if ($delete || $remake) {
			$form->query('xpath:.//button[@title="Delete"]')->one()->click();

			if ($remake) {
				$form->query('button:Add new data set')->one()->click();
				$form->invalidate();
			}
		}

		if (!$delete) {
			$this->fillDatasets($this->extractDataSets($fields), $form);
		}

		// Fill the other tabs.
		foreach (['Displaying options', 'Time period', 'Legend'] as $tab) {
			if (array_key_exists($tab, $fields)) {
				$form->selectTab($tab);
				$form->fill($fields[$tab]);
			}
		}
	}

	/**
	 * Fill "Data sets" tab with field data.
	 *
	 * @param array        $data_sets    array of data sets to be filled
	 * @param CFormElement $form         CFormElement to be filled
	 */
	protected function fillDatasets($data_sets, $form) {
		// Count of data sets that already exist (needed for updating).
		$count_sets = $form->query('xpath:.//li[contains(@class, "list-accordion-item")]')->all()->count();

		foreach ($data_sets as $i => $data_set) {
			$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
			unset($data_set['type']);

			// Special case: the first Data set is of type Item list.
			$deleted_first_set = false;
			if ($i === 0 && $type === self::TYPE_ITEM_LIST && $count_sets === 1) {
				$form->query('xpath:.//button[@title="Delete"]')->one()->click();
				$deleted_first_set = true;
			}

			// Open the Data set or create a new one.
			if ($i + 1 < $count_sets) {
				$form->query('xpath:.//li[contains(@class, "list-accordion-item")]['.
						($i + 1).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
			}
			else if ($i !== 0 || $deleted_first_set) {
				// Only add a new Data set if it is not the first one or the first one was deleted.
				$this->addNewDataSet($form, $type);
			}

			$form->invalidate();

			// Need additional steps when Data set type is Item list, but only if Host is set at all.
			if ($type === self::TYPE_ITEM_LIST && array_key_exists('host', $data_set)) {
				// Select Host.
				$form->query('button:Add')->one()->click();
				$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$select = $dialog->query('class:multiselect-control')->asMultiselect()->one();
				$select->fill($data_set['host']);
				unset($data_set['host']);

				// Select Items.
				$table = $dialog->query('class:list-table')->asTable()->waitUntilVisible()->one();
				foreach ($data_set['items'] as $item) {
					$table->findRow('Name', $item['name'])->select();
				}
				$dialog->getFooter()->query('button:Select')->one()->click();
				$dialog->waitUntilNotVisible();
			}

			$data_set = $this->remapDataSet($data_set, $i);
			$form->fill($data_set);
		}
	}

	/**
	 * Adds a new Data set of the correct type.
	 *
	 * @param CFormElement $form      widget edit form element
	 * @param string       $type      type of the data set
	 * @param boolean      $button    for "Item pattern" only: use "Add new data set" button if true, or select from context menu if false
	 */
	protected function addNewDataSet($form, $type = null, $button = true) {
		if (($type === self::TYPE_ITEM_PATTERN || $type === null) && $button) {
			$form->query('button:Add new data set')->one()->click();
		}
		else {
			$this->query('id:dataset-menu')->asPopupButton()->one()->select($type);
		}
	}

	/**
	 * Exchanges generic field names for the actual field selectors in a Data set form.
	 *
	 * @param array $data_set    Data set data
	 * @param int   $number      the position of this data set in UI
	 *
	 * @return array             remapped Data set
	 */
	protected function remapDataSet($data_set, $number) {
		// Key - selector mapping.
		$mapping = [
			'host' => 'xpath:.//div[@id="ds_'.$number.'_hosts_"]/..',
			'item' => 'xpath:.//div[@id="ds_'.$number.'_items_"]/..',
			'color' => 'xpath:.//input[@id="ds_'.$number.'_color"]/..',
			'il_color' => 'xpath:.//input[@id="items_'.$number.'_{id}_color"]/..',
			'il_type' => 'xpath:.//z-select[@id="items_'.$number.'_{id}_type"]'
		];

		// Exchange the keys for the actual selectors and clear the old key.
		foreach ($data_set as $data_set_key => $data_set_value) {
			// Only change mapped keys.
			if (array_key_exists($data_set_key, $mapping)) {
				$data_set += [$mapping[$data_set_key] => $data_set_value];
				unset($data_set[$data_set_key]);
			}
		}

		// Also map item fields for Item list.
		if (array_key_exists('items', $data_set)) {
			// An Item list can have several items.
			foreach ($data_set['items'] as $item_id => $item) {

				// An item can have several fields.
				foreach ($item as $field_key => $field_value) {
					if (array_key_exists($field_key, $mapping)) {
						// Set the item ID in selector, it starts at 1.
						$mapped_value = str_replace('{id}', $item_id + 1, $mapping[$field_key]);
						$data_set += [$mapped_value => $field_value];
					}
				}
			}

			unset($data_set['items']);
		}

		return $data_set;
	}

	/**
	 * Takes field data from a data provider and sets the defaults for Data sets.
	 *
	 * @param array $fields    field data from data provider
	 *
	 * @return array           field data with default values set
	 */
	protected function extractDataSets($fields) {
		$data_sets = array_key_exists('Data set', $fields)
			? $fields['Data set']
			: ['host' => 'Test Host', 'item' => 'Test Item'];

		if (CTestArrayHelper::isAssociative($data_sets)) {
			$data_sets = [$data_sets];
		}

		foreach ($data_sets as $i => $data_set) {
			if ($data_set === []) {
				$data_sets[$i] = ['host' => 'Test Host '.$i, 'item' => 'Test Item '.$i];
			}
		}

		return $data_sets;
	}

	/**
	 * Checks the 'placeholder' and 'maxlength' attributes of a field.
	 *
	 * @param CFormElement $form                form element of the field
	 * @param string       $name                name (or selector) of the field
	 * @param array        $attributes          the expected placeholder value (null skips this check)
	 * @param bool         $find_in_children    true if the real input field is actually a child of the form field element
	 */
	protected function assertFieldAttributes($form, $name, $attributes, $find_in_children = false) {
		$input = $form->getField($name);

		if ($find_in_children) {
			$input = $input->query('tag:input')->one();
		}

		foreach ($attributes as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $input->getAttribute($attribute));
		}
	}

	/**
	 * Opens the Pie chart dashboard.
	 *
	 * @param bool login            skips logging in if set to false
	 *
	 * @return CDashboardElement    dashboard element of the Pie chart dashboard
	 */
	protected function openDashboard($login = true) {
		if ($login) {
			$this->page->login();
		}

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		return CDashboardElement::find()->one();
	}

	/**
	 * Checks all visible labels inside an element. Fails if a label is missing or if there are unexpected labels.
	 *
	 * @param CElement $element    form element to check
	 * @param array    $labels     list of all currently visible labels
	 */
	protected function assertAllVisibleLabels($element, $labels) {
		// There are weird labels in this form but at the same time we don't need to match all of them, for example radio buttons.
		$label_selector = 'xpath:.//div[@class="form-grid"]/label'. // standart case
				' | .//div[@class="form-field"]/label'. // when the label is a child of the actual field
				' | .//label[@class="sortable-drag-handle js-dataset-label"]'; // this matches data set labels

		$actual_labels = $element->query($label_selector)->all()->filter(CElementFilter::VISIBLE)->asText();

		// Remove empty labels (these come from checkboxes) from the list.
		$actual_labels = array_filter($actual_labels);

		$this->assertEqualsCanonicalizing($labels, $actual_labels,
				'The expected visible labels and the actual visible labels are different.'
		);
	}
}
