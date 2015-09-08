<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CMacrosResolver extends CMacrosResolverGeneral {

	/**
	 * Supported macros resolving scenarios.
	 *
	 * @var array
	 */
	protected $configs = [
		'scriptConfirmation' => [
			'types' => ['host', 'interfaceWithoutPort', 'user'],
			'method' => 'resolveTexts'
		],
		'httpTestName' => [
			'types' => ['host', 'interfaceWithoutPort', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfaceIpDns' => [
			'types' => ['host', 'agentInterface', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfaceIpDnsAgentPrimary' => [
			'types' => ['host', 'user'],
			'method' => 'resolveTexts'
		],
		'hostInterfacePort' => [
			'types' => ['user'],
			'method' => 'resolveTexts'
		],
		'triggerName' => [
			'types' => ['host', 'interface', 'user', 'item', 'reference'],
			'source' => 'description',
			'method' => 'resolveTrigger'
		],
		'triggerDescription' => [
			'types' => ['host', 'interface', 'user', 'item'],
			'source' => 'comments',
			'method' => 'resolveTrigger'
		],
		'triggerExpressionUser' => [
			'types' => ['user'],
			'source' => 'expression',
			'method' => 'resolveTrigger'
		],
		'triggerUrl' => [
			'types' => ['trigger', 'host2', 'interface2', 'user'],
			'source' => 'url',
			'method' => 'resolveTrigger'
		],
		'eventDescription' => [
			'types' => ['host', 'interface', 'user', 'item', 'reference'],
			'source' => 'description',
			'method' => 'resolveTrigger'
		],
		'graphName' => [
			'types' => ['graphFunctionalItem'],
			'source' => 'name',
			'method' => 'resolveGraph'
		],
		'screenElementURL' => [
			'types' => ['host', 'hostId', 'interfaceWithoutPort', 'user'],
			'source' => 'url',
			'method' => 'resolveTexts'
		],
		'screenElementURLUser' => [
			'types' => ['user'],
			'source' => 'url',
			'method' => 'resolveTexts'
		]
	];

	/**
	 * Resolve macros.
	 *
	 * Macros examples:
	 * reference: $1, $2, $3, ...
	 * user: {$MACRO1}, {$MACRO2}, ...
	 * host: {HOSTNAME}, {HOST.HOST}, {HOST.NAME}
	 * ip: {IPADDRESS}, {HOST.IP}, {HOST.DNS}, {HOST.CONN}
	 * item: {ITEM.LASTVALUE}, {ITEM.VALUE}
	 *
	 * @param array  $options
	 * @param string $options['config']
	 * @param array  $options['data']
	 *
	 * @return array
	 */
	public function resolve(array $options) {
		if (empty($options['data'])) {
			return [];
		}

		$this->config = $options['config'];

		// call method
		$method = $this->configs[$this->config]['method'];

		return $this->$method($options['data']);
	}

	/**
	 * Batch resolving macros in text using host id.
	 *
	 * @param array $data	(as $hostId => array(texts))
	 *
	 * @return array		(as $hostId => array(texts))
	 */
	private function resolveTexts(array $data) {
		$hostIds = array_keys($data);

		$macros = [];

		$hostMacrosAvailable = false;
		$agentInterfaceAvailable = false;
		$interfaceWithoutPortMacrosAvailable = false;

		if ($this->isTypeAvailable('host')) {
			foreach ($data as $hostid => $texts) {
				if ($hostMacros = $this->findMacros(self::PATTERN_HOST, $texts)) {
					foreach ($hostMacros as $hostMacro) {
						$macros[$hostid][$hostMacro] = UNRESOLVED_MACRO_STRING;
					}

					$hostMacrosAvailable = true;
				}
			}
		}

		if ($this->isTypeAvailable('hostId')) {
			foreach ($data as $hostid => $texts) {
				if ($hostid != 0) {
					$hostIdMacros = $this->findMacros(self::PATTERN_HOST_ID, $texts);
					if ($hostIdMacros) {
						foreach ($hostIdMacros as $hostMacro) {
							$macros[$hostid][$hostMacro] = $hostid;
						}
					}
				}
			}
		}

		if ($this->isTypeAvailable('agentInterface')) {
			foreach ($data as $hostid => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostid][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$agentInterfaceAvailable = true;
				}
			}
		}

		if ($this->isTypeAvailable('interfaceWithoutPort')) {
			foreach ($data as $hostid => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					foreach ($interfaceMacros as $interfaceMacro) {
						$macros[$hostid][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
					}

					$interfaceWithoutPortMacrosAvailable = true;
				}
			}
		}

		// host macros
		if ($hostMacrosAvailable) {
			$dbHosts = DBselect('SELECT h.hostid,h.name,h.host FROM hosts h WHERE '.dbConditionInt('h.hostid', $hostIds));

			while ($dbHost = DBfetch($dbHosts)) {
				$hostid = $dbHost['hostid'];

				if ($hostMacros = $this->findMacros(self::PATTERN_HOST, $data[$hostid])) {
					foreach ($hostMacros as $hostMacro) {
						switch ($hostMacro) {
							case '{HOSTNAME}':
							case '{HOST.HOST}':
								$macros[$hostid][$hostMacro] = $dbHost['host'];
								break;

							case '{HOST.NAME}':
								$macros[$hostid][$hostMacro] = $dbHost['name'];
								break;
						}
					}
				}
			}
		}

		// interface macros, macro should be resolved to main agent interface
		if ($agentInterfaceAvailable) {
			foreach ($data as $hostid => $texts) {
				if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $texts)) {
					$dbInterface = DBfetch(DBselect(
						'SELECT i.hostid,i.ip,i.dns,i.useip'.
						' FROM interface i'.
						' WHERE i.main='.INTERFACE_PRIMARY.
							' AND i.type='.INTERFACE_TYPE_AGENT.
							' AND i.hostid='.zbx_dbstr($hostid)
					));

					$dbInterfaceTexts = [$dbInterface['ip'], $dbInterface['dns']];

					if ($this->findMacros(self::PATTERN_HOST, $dbInterfaceTexts)
							|| $this->findUserMacros($dbInterfaceTexts)) {
						$saveCurrentConfig = $this->config;

						$dbInterfaceMacros = $this->resolve([
							'config' => 'hostInterfaceIpDnsAgentPrimary',
							'data' => [$hostid => $dbInterfaceTexts]
						]);

						$dbInterfaceMacros = reset($dbInterfaceMacros);
						$dbInterface['ip'] = $dbInterfaceMacros[0];
						$dbInterface['dns'] = $dbInterfaceMacros[1];

						$this->config = $saveCurrentConfig;
					}

					foreach ($interfaceMacros as $interfaceMacro) {
						switch ($interfaceMacro) {
							case '{IPADDRESS}':
							case '{HOST.IP}':
								$macros[$hostid][$interfaceMacro] = $dbInterface['ip'];
								break;

							case '{HOST.DNS}':
								$macros[$hostid][$interfaceMacro] = $dbInterface['dns'];
								break;

							case '{HOST.CONN}':
								$macros[$hostid][$interfaceMacro] = $dbInterface['useip']
									? $dbInterface['ip']
									: $dbInterface['dns'];
								break;
						}
					}
				}
			}
		}

		// interface macros, macro should be resolved to interface with highest priority
		if ($interfaceWithoutPortMacrosAvailable) {
			$interfaces = [];

			$dbInterfaces = DBselect(
				'SELECT i.hostid,i.ip,i.dns,i.useip,i.type'.
				' FROM interface i'.
				' WHERE i.main='.INTERFACE_PRIMARY.
					' AND '.dbConditionInt('i.hostid', $hostIds).
					' AND '.dbConditionInt('i.type', $this->interfacePriorities)
			);

			while ($dbInterface = DBfetch($dbInterfaces)) {
				$hostid = $dbInterface['hostid'];

				if (isset($interfaces[$hostid])) {
					$dbPriority = $this->interfacePriorities[$dbInterface['type']];
					$existPriority = $this->interfacePriorities[$interfaces[$hostid]['type']];

					if ($dbPriority > $existPriority) {
						$interfaces[$hostid] = $dbInterface;
					}
				}
				else {
					$interfaces[$hostid] = $dbInterface;
				}
			}

			if ($interfaces) {
				foreach ($interfaces as $hostid => $interface) {
					if ($interfaceMacros = $this->findMacros(self::PATTERN_INTERFACE, $data[$hostid])) {
						foreach ($interfaceMacros as $interfaceMacro) {
							switch ($interfaceMacro) {
								case '{IPADDRESS}':
								case '{HOST.IP}':
									$macros[$hostid][$interfaceMacro] = $interface['ip'];
									break;

								case '{HOST.DNS}':
									$macros[$hostid][$interfaceMacro] = $interface['dns'];
									break;

								case '{HOST.CONN}':
									$macros[$hostid][$interfaceMacro] = $interface['useip']
										? $interface['ip']
										: $interface['dns'];
									break;
							}

							// Resolving macros to AGENT main interface. If interface is AGENT macros stay unresolved.
							if ($interface['type'] != INTERFACE_TYPE_AGENT) {
								if ($this->findMacros(self::PATTERN_HOST, [$macros[$hostid][$interfaceMacro]])
										|| $this->findUserMacros([$macros[$hostid][$interfaceMacro]])) {
									// attention recursion!
									$macrosInMacros = $this->resolveTexts([$hostid => [$macros[$hostid][$interfaceMacro]]]);
									$macros[$hostid][$interfaceMacro] = $macrosInMacros[$hostid][0];
								}
								elseif ($this->findMacros(self::PATTERN_INTERFACE, [$macros[$hostid][$interfaceMacro]])) {
									$macros[$hostid][$interfaceMacro] = UNRESOLVED_MACRO_STRING;
								}
							}
						}
					}
				}
			}
		}

		// get user macros
		if ($this->isTypeAvailable('user')) {
			$usermacros_data = [];

			foreach ($data as $hostid => $texts) {
				$usermacros = $this->findUserMacros($texts);

				foreach ($usermacros as $usermacro) {
					if (!array_key_exists($hostid, $usermacros_data)) {
						$usermacros_data[$hostid] = [
							'hostids' => [$hostid],
							'macros' => []
						];
					}

					$usermacros_data[$hostid]['macros'][$usermacro] = null;
				}
			}

			$usermacros = $this->getUserMacros($usermacros_data);

			foreach ($usermacros as $hostid => $usermacro) {
				$macros[$hostid] = array_key_exists($hostid, $macros)
					? array_merge($macros[$hostid], $usermacro['macros'])
					: $usermacro['macros'];
			}
		}

		// replace macros to value
		if ($macros) {
			$pattern = '/'.self::PATTERN_HOST.'|'.self::PATTERN_HOST_ID.'|'.self::PATTERN_INTERFACE.'/';

			foreach ($data as $hostid => $texts) {
				if (array_key_exists($hostid, $macros)) {
					foreach ($texts as $tnum => $text) {
						preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

						for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
							$matche = $matches[0][$i];

							$macrosValue = isset($macros[$hostid][$matche[0]]) ? $macros[$hostid][$matche[0]] : $matche[0];
							$text = substr_replace($text, $macrosValue, $matche[1], strlen($matche[0]));
						}

						$data[$hostid][$tnum] = $text;
					}

					foreach ($texts as $tnum => $text) {
						$parser = new CUserMacroParser($text);

						if ($parser->isValid()) {
							$data[$hostid][$tnum] = $macros[$hostid][$parser->getMacros()[0]['macro']];
						}
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Resolve macros in trigger.
	 *
	 * @param string $triggers[$triggerId]['expression']
	 * @param string $triggers[$triggerId]['description']			depend from config
	 * @param string $triggers[$triggerId]['comments']				depend from config
	 * @param string $triggers[$triggerId]['url']					depend from config
	 *
	 * @return array
	 */
	private function resolveTrigger(array $triggers) {
		$macros = [
			'host' => [],
			'host2' => [],
			'interfaceWithoutPort' => [],
			'interface' => [],
			'interface2' => [],
			'item' => []
		];
		$macro_values = [];
		$usermacros_data = [];

		// get source field
		$source = $this->getSource();

		// get available functions
		$hostMacrosAvailable = $this->isTypeAvailable('host');
		$hostMacrosAvailable2 = $this->isTypeAvailable('host2');
		$interfaceWithoutPortMacrosAvailable = $this->isTypeAvailable('interfaceWithoutPort');
		$interfaceMacrosAvailable = $this->isTypeAvailable('interface');
		$interfaceMacrosAvailable2 = $this->isTypeAvailable('interface2');
		$itemMacrosAvailable = $this->isTypeAvailable('item');
		$userMacrosAvailable = $this->isTypeAvailable('user');
		$referenceMacrosAvailable = $this->isTypeAvailable('reference');
		$triggerMacrosAvailable = $this->isTypeAvailable('trigger');

		// find macros
		foreach ($triggers as $triggerid => $trigger) {
			if ($userMacrosAvailable) {
				$usermacros = $this->findUserMacros([$trigger[$source]]);

				if ($usermacros) {
					if (!array_key_exists($triggerid, $usermacros_data)) {
						$usermacros_data[$triggerid] = ['macros' => [], 'hostids' => []];
					}

					foreach ($usermacros as $usermacro) {
						$usermacros_data[$triggerid]['macros'][$usermacro] = null;
					}
				}
			}

			$functions = $this->findFunctions($trigger['expression']);

			if ($hostMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_HOST_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['host'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($hostMacrosAvailable2) {
				$foundMacros = $this->findFunctionMacros(self::PATTERN_HOST_FUNCTION2, $trigger[$source]);
				foreach ($foundMacros as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['host2'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($interfaceWithoutPortMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION_WITHOUT_PORT, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interfaceWithoutPort'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($interfaceMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interface'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($interfaceMacrosAvailable2) {
				$foundMacros = $this->findFunctionMacros(self::PATTERN_INTERFACE_FUNCTION2, $trigger[$source]);
				foreach ($foundMacros as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['interface2'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($itemMacrosAvailable) {
				foreach ($this->findFunctionMacros(self::PATTERN_ITEM_FUNCTION, $trigger[$source]) as $macro => $fNums) {
					foreach ($fNums as $fNum) {
						$macro_values[$triggerid][$this->getFunctionMacroName($macro, $fNum)] = UNRESOLVED_MACRO_STRING;

						if (isset($functions[$fNum])) {
							$macros['item'][$functions[$fNum]][$macro][] = $fNum;
						}
					}
				}
			}

			if ($referenceMacrosAvailable) {
				foreach ($this->getTriggerReference($trigger['expression'], $trigger[$source]) as $macro => $value) {
					$macro_values[$triggerid][$macro] = $value;
				}
			}

			if ($triggerMacrosAvailable) {
				foreach ($this->findMacros(self::PATTERN_TRIGGER, [$trigger[$source]]) as $macro) {
					$macro_values[$triggerid][$macro] = $triggerid;
				}
			}
		}

		$patterns = [];

		// get macro value
		if ($hostMacrosAvailable) {
			$macro_values = $this->getHostMacros($macros['host'], $macro_values);
			$patterns[] = self::PATTERN_HOST_FUNCTION;
		}

		if ($hostMacrosAvailable2) {
			$macro_values = $this->getHostMacros($macros['host2'], $macro_values);
			$patterns[] = self::PATTERN_HOST_FUNCTION2;
		}

		if ($interfaceWithoutPortMacrosAvailable) {
			$macro_values = $this->getIpMacros($macros['interfaceWithoutPort'], $macro_values, false);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION_WITHOUT_PORT;
		}

		if ($interfaceMacrosAvailable) {
			$macro_values = $this->getIpMacros($macros['interface'], $macro_values, true);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION;
		}

		if ($interfaceMacrosAvailable2) {
			$macro_values = $this->getIpMacros($macros['interface2'], $macro_values, true);
			$patterns[] = self::PATTERN_INTERFACE_FUNCTION2;
		}

		if ($itemMacrosAvailable) {
			$macro_values = $this->getItemMacros($macros['item'], $triggers, $macro_values);
			$patterns[] = self::PATTERN_ITEM_FUNCTION;
		}

		if ($usermacros_data) {
			// Get hosts for triggers.
			$dbTriggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectHosts' => ['hostid'],
				'triggerids' => array_keys($usermacros_data),
				'preservekeys' => true
			]);

			foreach ($usermacros_data as $triggerid => $usermacro) {
				if (array_key_exists($triggerid, $dbTriggers)) {
					$usermacros_data[$triggerid]['hostids'] =
						zbx_objectValues($dbTriggers[$triggerid]['hosts'], 'hostid');
				}
			}

			// Get user macros values.
			$usermacros = $this->getUserMacros($usermacros_data);

			foreach ($usermacros as $triggerid => $usermacro) {
				$macro_values[$triggerid] = array_key_exists($triggerid, $macro_values)
					? array_merge($macro_values[$triggerid], $usermacro['macros'])
					: $usermacro['macros'];
			}
		}

		if ($referenceMacrosAvailable) {
			$patterns[] = '\$([1-9])';
		}

		if ($triggerMacrosAvailable) {
			$patterns[] = self::PATTERN_TRIGGER;
		}

		$pattern = '/'.implode('|', $patterns).'/';

		// Replace macros to values.
		foreach ($triggers as $triggerid => &$trigger) {
			preg_match_all($pattern, $trigger[$source], $matches, PREG_OFFSET_CAPTURE);

			for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
				$matche = $matches[0][$i];

				$macrosValue = isset($macro_values[$triggerid][$matche[0]])
					? $macro_values[$triggerid][$matche[0]]
					: $matche[0];
				$trigger[$source] = substr_replace($trigger[$source], $macrosValue, $matche[1], strlen($matche[0]));
			}
		}
		unset($trigger);

		if ($macro_values) {
			foreach ($triggers as $triggerid => &$trigger) {
				$trigger[$source] = $this->replaceUserMacros($trigger[$source], $macro_values[$triggerid]);
			}
			unset($trigger);
		}

		return $triggers;
	}

	/**
	 * Expand reference macros for trigger.
	 * If macro reference non existing value it expands to empty string.
	 *
	 * @param string $expression
	 * @param string $text
	 *
	 * @return string
	 */
	public function resolveTriggerReference($expression, $text) {
		foreach ($this->getTriggerReference($expression, $text) as $key => $value) {
			$text = str_replace($key, $value, $text);
		}

		return $text;
	}

	/**
	 * Resolve functional item macros, for example, {{HOST.HOST1}:key.func(param)}.
	 *
	 * @param array  $graphs							list or hashmap of graphs
	 * @param string $graphs[]['name']				string in which macros should be resolved
	 * @param array  $graphs[]['items']				list of graph items
	 * @param int    $graphs[]['items'][n]['hostid']	graph n-th item corresponding host Id
	 * @param string $graphs[]['items'][n]['host']	graph n-th item corresponding host name
	 *
	 * @return string	inputted data with resolved source field
	 */
	private function resolveGraph($graphs) {
		if ($this->isTypeAvailable('graphFunctionalItem')) {
			$sourceKeyName = $this->getSource();

			$sourceStringList = [];
			$itemsList = [];

			foreach ($graphs as $graph) {
				$sourceStringList[] = $graph[$sourceKeyName];
				$itemsList[] = $graph['items'];
			}

			$resolvedStringList = $this->resolveGraphsFunctionalItemMacros($sourceStringList, $itemsList);
			$resolvedString = reset($resolvedStringList);

			foreach ($graphs as &$graph) {
				$graph[$sourceKeyName] = $resolvedString;
				$resolvedString = next($resolvedStringList);
			}
			unset($graph);
		}

		return $graphs;
	}

	/**
	 * Resolve functional macros, like {hostname:key.function(param)}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*".
	 *
	 * Supports function "last", "min", "max" and "avg".
	 * Supports seconds as parameters, except "last" function.
	 * Second parameter like {hostname:key.last(0,86400) and offsets like {hostname:key.last(#1)} are not supported.
	 * Supports postfixes s,m,h,d and w for parameter.
	 *
	 * @param array  $sourceStringList			list of strings from graphs in which macros should be resolved
	 * @param array  $itemsList					list of lists of graph items used in graphs
	 * @param int    $itemsList[n][m]['hostid']	n-th graph m-th item corresponding host ID
	 * @param string $itemsList[n][m]['host']	n-th graph m-th item corresponding host name
	 *
	 * @return array	list of strings, possibly with macros in them replaced with resolved values
	 */
	private function resolveGraphsFunctionalItemMacros(array $sourceStringList, array $itemsList) {
		$hostKeyPairs = [];
		$matchesList = [];

		$items = reset($itemsList);
		foreach ($sourceStringList as $sourceString) {
			// Extract all macros into $matches - keys: macros, hosts, keys, functions and parameters are used
			// searches for macros, for example, "{somehost:somekey["param[123]"].min(10m)}"
			preg_match_all('/(?P<macros>{'.
				'(?P<hosts>('.ZBX_PREG_HOST_FORMAT.'|({('.self::PATTERN_HOST_INTERNAL.')'.self::PATTERN_MACRO_PARAM.'}))):'.
				'(?P<keys>'.ZBX_PREG_ITEM_KEY_FORMAT.')\.'.
				'(?P<functions>(last|max|min|avg))\('.
				'(?P<parameters>([0-9]+['.ZBX_TIME_SUFFIXES.']?)?)'.
				'\)}{1})/Uux', $sourceString, $matches, PREG_OFFSET_CAPTURE);

			foreach ($matches['hosts'] as $i => &$host) {
				$host[0] = $this->resolveGraphPositionalMacros($host[0], $items);

				if ($host[0] !== UNRESOLVED_MACRO_STRING) {
					// Take note that resolved host has a such key (and it is used in a macro).
					if (!isset($hostKeyPairs[$host[0]])) {
						$hostKeyPairs[$host[0]] = [];
					}
					$hostKeyPairs[$host[0]][$matches['keys'][$i][0]] = true;
				}
			}
			unset($host);

			// Remember match for later use.
			$matchesList[] = $matches;

			$items = next($itemsList);
		}

		// If no host/key pairs found in macro-like parts of source string then there is nothing to do but return
		// source strings as they are.
		if (!$hostKeyPairs) {
			return $sourceStringList;
		}

		// Build item retrieval query from host-key pairs and get all necessary items for all source strings
		$queryParts = [];
		foreach ($hostKeyPairs as $host => $keys) {
			$queryParts[] = '(h.host='.zbx_dbstr($host).' AND '.dbConditionString('i.key_', array_keys($keys)).')';
		}
		$items = DBfetchArrayAssoc(DBselect(
			'SELECT h.host,i.key_,i.itemid,i.value_type,i.units,i.valuemapid'.
			' FROM items i,hosts h'.
			' WHERE i.hostid=h.hostid'.
				' AND ('.join(' OR ', $queryParts).')'
		), 'itemid');

		// Get items for which user has permission ...
		$allowedItems = API::Item()->get([
			'itemids' => array_keys($items),
			'webitems' => true,
			'output' => ['itemid', 'value_type', 'lastvalue', 'lastclock'],
			'preservekeys' => true
		]);

		// ... and map item data only for those allowed items and set "value_type" for allowed items.
		foreach ($items as $item) {
			if (isset($allowedItems[$item['itemid']])) {
				$item['lastvalue'] = $allowedItems[$item['itemid']]['lastvalue'];
				$item['lastclock'] = $allowedItems[$item['itemid']]['lastclock'];
				$hostKeyPairs[$item['host']][$item['key_']] = $item;
			}
		}


		// replace macros with their corresponding values in graph strings
		// Replace macros with their resolved values in source strings.
		$matches = reset($matchesList);
		foreach ($sourceStringList as &$sourceString) {
			// We iterate array backwards so that replacing unresolved macro string (see lower) with actual value
			// does not mess up originally captured offsets!
			$i = count($matches['macros']);

			while ($i--) {
				$host = $matches['hosts'][$i][0];
				$key = $matches['keys'][$i][0];
				$function = $matches['functions'][$i][0];
				$parameter = $matches['parameters'][$i][0];

				// If host is real and item exists and has permissions
				if ($host !== UNRESOLVED_MACRO_STRING && is_array($hostKeyPairs[$host][$key])) {
					$item = $hostKeyPairs[$host][$key];

					// macro function is "last"
					if ($function == 'last') {
						$value = ($item['lastclock'] > 0)
							? formatHistoryValue($item['lastvalue'], $item)
							: UNRESOLVED_MACRO_STRING;
					}
					// For other macro functions ("max", "min" or "avg") get item value.
					else {
						$value = getItemFunctionalValue($item, $function, $parameter);
					}
				}
				// Or if there is no item with given key in given host, or there is no permissions to that item
				else {
					$value = UNRESOLVED_MACRO_STRING;
				}

				// Replace macro string with actual, resolved string value. This is safe because we start from far
				// end of $sourceString.
				$sourceString = substr_replace($sourceString, $value, $matches['macros'][$i][1],
					strlen($matches['macros'][$i][0])
				);
			}

			// Advance to next matches for next $sourceString
			$matches = next($matchesList);
		}
		unset($sourceString);

		return $sourceStringList;
	}

	/**
	 * Resolve positional macros, like {HOST.HOST2}.
	 * If macro can not be resolved it is replaced with UNRESOLVED_MACRO_STRING string i.e. "*UNKNOWN*"
	 * Supports HOST.HOST<1..9> macros.
	 *
	 * @param string	$str				string in which macros should be resolved
	 * @param array		$items				list of graph items
	 * @param int 		$items[n]['hostid'] graph n-th item corresponding host Id
	 * @param string	$items[n]['host']   graph n-th item corresponding host name
	 *
	 * @return string	string with macros replaces with corresponding values
	 */
	private function resolveGraphPositionalMacros($str, $items) {
		// extract all macros into $matches
		preg_match_all('/{(('.self::PATTERN_HOST_INTERNAL.')('.self::PATTERN_MACRO_PARAM.'))\}/', $str, $matches);

		// match found groups if ever regexp should change
		$matches['macroType'] = $matches[2];
		$matches['position'] = $matches[3];

		// build structure of macros: $macroList['HOST.HOST'][2] = 'host name';
		$macroList = [];

		// $matches[3] contains positions, e.g., '',1,2,2,3,...
		foreach ($matches['position'] as $i => $position) {
			// take care of macro without positional index
			$posInItemList = ($position === '') ? 0 : $position - 1;

			// init array
			if (!isset($macroList[$matches['macroType'][$i]])) {
				$macroList[$matches['macroType'][$i]] = [];
			}

			// skip computing for duplicate macros
			if (isset($macroList[$matches['macroType'][$i]][$position])) {
				continue;
			}

			// positional index larger than item count, resolve to UNKNOWN
			if (!isset($items[$posInItemList])) {
				$macroList[$matches['macroType'][$i]][$position] = UNRESOLVED_MACRO_STRING;

				continue;
			}

			// retrieve macro replacement data
			switch ($matches['macroType'][$i]) {
				case 'HOSTNAME':
				case 'HOST.HOST':
					$macroList[$matches['macroType'][$i]][$position] = $items[$posInItemList]['host'];
					break;
			}
		}

		// replace macros with values in $str
		foreach ($macroList as $macroType => $positions) {
			foreach ($positions as $position => $replacement) {
				$str = str_replace('{'.$macroType.$position.'}', $replacement, $str);
			}
		}

		return $str;
	}

	/**
	 * Resolve item name macros to "name_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['name']
	 * @param string $items[n]['key_']				item key (optional)
	 *												but is (mandatory) if macros exist and "key_expanded" is not present
	 * @param string $items[n]['key_expanded']		expanded item key (optional)
	 *
	 * @return array
	 */
	public function resolveItemNames(array $items) {
		// define resolving fields
		foreach ($items as &$item) {
			$item['name_expanded'] = $item['name'];
		}
		unset($item);

		$macros = [];
		$itemsWithUnResolvedKeys = [];
		$itemsWithReferenceMacros = [];

		// reference macros - $1..$9
		foreach ($items as $key => $item) {
			$matched_macros = $this->findMacros(self::PATTERN_ITEM_NUMBER, [$item['name_expanded']]);

			if ($matched_macros) {
				$macros[$key] = ['macros' => []];

				foreach ($matched_macros as $matched_macro) {
					$macros[$key]['macros'][$matched_macro] = null;
				}

				$itemsWithReferenceMacros[$key] = $item;
			}
		}

		if ($itemsWithReferenceMacros) {
			// resolve macros in item key
			foreach ($itemsWithReferenceMacros as $key => $item) {
				if (!isset($item['key_expanded'])) {
					$itemsWithUnResolvedKeys[$key] = $item;
				}
			}

			if ($itemsWithUnResolvedKeys) {
				$itemsWithUnResolvedKeys = $this->resolveItemKeys($itemsWithUnResolvedKeys);

				foreach ($itemsWithUnResolvedKeys as $key => $item) {
					$itemsWithReferenceMacros[$key] = $item;
				}
			}

			// reference macros - $1..$9
			foreach ($itemsWithReferenceMacros as $key => $item) {
				$itemKey = new CItemKey($item['key_expanded']);

				if ($itemKey->isValid()) {
					foreach ($itemKey->getParameters() as $n => $keyParameter) {
						$paramNum = '$'.++$n;

						if (array_key_exists($paramNum, $macros[$key]['macros'])) {
							$macros[$key]['macros'][$paramNum] = $keyParameter;
						}
					}
				}
			}
		}

		// user macros
		$usermacros = [];

		// Find user macros in strings.
		foreach ($items as $item) {
			$matched_macros = $this->findUserMacros([$item['name_expanded']]);

			foreach ($matched_macros as $matched_macro) {
				if (!array_key_exists($item['hostid'], $usermacros)) {
					$usermacros[$item['hostid']] = [
						'hostids' => [$item['hostid']],
						'macros' => []
					];
				}

				$usermacros[$item['hostid']]['macros'][$matched_macro] = null;
			}
		}

		// Get values for user macros.
		if ($usermacros) {
			$usermacros = $this->getUserMacros($usermacros);

			foreach ($items as $key => $item) {
				if (array_key_exists($item['hostid'], $usermacros)) {
					$macros[$key]['macros'] = array_key_exists($key, $macros)
						? zbx_array_merge($macros[$key]['macros'], $usermacros[$item['hostid']]['macros'])
						: $usermacros[$item['hostid']]['macros'];
				}
			}
		}

		// Replace macros to values one by one.
		if ($macros) {
			foreach ($macros as $key => $macro_data) {
				$items[$key]['name_expanded'] = $this->replaceUserMacros($items[$key]['name_expanded'],
					$macro_data['macros']
				);

				// Replace reference macros.
				if (array_key_exists($key, $itemsWithReferenceMacros)) {
					$items[$key]['name_expanded'] = $this->replaceReferenceMacros($items[$key]['name_expanded'],
						$macro_data['macros']
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Resolve item key macros to "key_expanded" field.
	 *
	 * @param array  $items
	 * @param string $items[n]['itemid']
	 * @param string $items[n]['hostid']
	 * @param string $items[n]['key_']
	 *
	 * @return array
	 */
	public function resolveItemKeys(array $items) {
		// define resolving field
		foreach ($items as &$item) {
			$item['key_expanded'] = $item['key_'];
		}
		unset($item);

		$macros = [];
		$itemIds = [];

		// host, ip macros
		foreach ($items as $key => $item) {
			$matched_macros = $this->findMacros(self::PATTERN_ITEM_MACROS, [$item['key_expanded']]);

			if ($matched_macros) {
				$itemIds[$item['itemid']] = $item['itemid'];

				$macros[$key] = [
					'itemid' => $item['itemid'],
					'macros' => []
				];

				foreach ($matched_macros as $matched_macro) {
					$macros[$key]['macros'][$matched_macro] = null;
				}
			}
		}

		if ($macros) {
			$dbItems = API::Item()->get([
				'itemids' => $itemIds,
				'selectInterfaces' => ['ip', 'dns', 'useip'],
				'selectHosts' => ['host', 'name'],
				'webitems' => true,
				'output' => ['itemid'],
				'filter' => ['flags' => null],
				'preservekeys' => true
			]);

			foreach ($macros as $key => $macro_data) {
				if (array_key_exists($macro_data['itemid'], $dbItems)) {
					$host = reset($dbItems[$macro_data['itemid']]['hosts']);
					$interface = reset($dbItems[$macro_data['itemid']]['interfaces']);

					// if item without interface or template item, resolve interface related macros to *UNKNOWN*
					if (!$interface) {
						$interface = [
							'ip' => UNRESOLVED_MACRO_STRING,
							'dns' => UNRESOLVED_MACRO_STRING,
							'useip' => false
						];
					}

					foreach ($macro_data['macros'] as $macro => $value) {
						switch ($macro) {
							case '{HOST.NAME}':
								$macros[$key]['macros'][$macro] = $host['name'];
								break;

							case '{HOST.HOST}':
							case '{HOSTNAME}': // deprecated
								$macros[$key]['macros'][$macro] = $host['host'];
								break;

							case '{HOST.IP}':
							case '{IPADDRESS}': // deprecated
								$macros[$key]['macros'][$macro] = $interface['ip'];
								break;

							case '{HOST.DNS}':
								$macros[$key]['macros'][$macro] = $interface['dns'];
								break;

							case '{HOST.CONN}':
								$macros[$key]['macros'][$macro] = $interface['useip']
									? $interface['ip']
									: $interface['dns'];
								break;
						}
					}
				}

				unset($macros[$key]['itemid']);
			}
		}

		// user macros
		$userMacros = [];

		foreach ($items as $item) {
			$itemKey = new CItemKey($item['key_expanded']);

			if ($itemKey->isValid()) {
				$matched_macros = $this->findUserMacros([$item['key_expanded']]);

				foreach ($matched_macros as $matched_macro) {
					if (!array_key_exists($item['hostid'], $userMacros)) {
						$userMacros[$item['hostid']] = [
							'hostids' => [$item['hostid']],
							'macros' => []
						];
					}

					$userMacros[$item['hostid']]['macros'][$matched_macro] = null;
				}
			}
		}

		if ($userMacros) {
			$userMacros = $this->getUserMacros($userMacros);

			foreach ($items as $key => $item) {
				if (isset($userMacros[$item['hostid']])) {
					$macros[$key]['macros'] = isset($macros[$key])
						? zbx_array_merge($macros[$key]['macros'], $userMacros[$item['hostid']]['macros'])
						: $userMacros[$item['hostid']]['macros'];
				}
			}
		}

		// Replace macros to value one by one.
		if ($macros) {
			foreach ($macros as $key => $macro_data) {
				$items[$key]['key_expanded'] = $this->replaceUserMacros($items[$key]['key_expanded'],
					$macro_data['macros']
				);
			}
		}

		return $items;
	}

	/**
	 * Resolve function parameter macros to "parameter_expanded" field.
	 *
	 * @param array  $data
	 * @param string $data[n]['hostid']
	 * @param string $data[n]['parameter']
	 *
	 * @return array
	 */
	public function resolveFunctionParameters(array $data) {
		// define resolving field
		foreach ($data as &$function) {
			$function['parameter_expanded'] = $function['parameter'];
		}
		unset($function);

		$macros = [];

		// user macros
		$usermacros = [];

		foreach ($data as $function) {
			$matched_macros = $this->findUserMacros([$function['parameter_expanded']]);

			if ($matched_macros) {
				foreach ($matched_macros as $matched_macro) {
					if (!array_key_exists($function['hostid'], $usermacros)) {
						$usermacros[$function['hostid']] = [
							'hostids' => [$function['hostid']],
							'macros' => []
						];
					}

					$usermacros[$function['hostid']]['macros'][$matched_macro] = null;
				}
			}
		}

		if ($usermacros) {
			$usermacros = $this->getUserMacros($usermacros);

			foreach ($data as $key => $function) {
				if (array_key_exists($function['hostid'], $usermacros)) {
					$macros[$key]['macros'] = array_key_exists($key, $macros)
						? zbx_array_merge($macros[$key]['macros'], $usermacros[$function['hostid']]['macros'])
						: $usermacros[$function['hostid']]['macros'];
				}
			}
		}

		// Replace macros to value one by one.
		if ($macros) {
			foreach ($macros as $key => $macro_data) {
				$data[$key]['parameter_expanded'] = $this->replaceUserMacros($data[$key]['parameter_expanded'],
					$macro_data['macros']
				);
			}
		}

		return $data;
	}

	/**
	 * Expand functional macros in given map label.
	 *
	 * @param string $label			label to expand
	 * @param array  $replaceHosts	list of hosts in order which they appear in trigger expression if trigger label is given,
	 * or single host when host label is given
	 *
	 * @return string
	 */
	public function resolveMapLabelMacros($label, $replaceHosts = null) {
		$functionsPattern = '(last|max|min|avg)\(([0-9]+['.ZBX_TIME_SUFFIXES.']?)?\)';

		// find functional macro pattern
		$pattern = ($replaceHosts === null)
			? '/{'.ZBX_PREG_HOST_FORMAT.':.+\.'.$functionsPattern.'}/Uu'
			: '/{('.ZBX_PREG_HOST_FORMAT.'|{HOSTNAME[0-9]?}|{HOST\.HOST[0-9]?}):.+\.'.$functionsPattern.'}/Uu';

		preg_match_all($pattern, $label, $matches);

		// for each functional macro
		foreach ($matches[0] as $expr) {
			$macro = $expr;

			if ($replaceHosts !== null) {
				// search for macros with all possible indices
				foreach ($replaceHosts as $i => $host) {
					$macroTmp = $macro;

					// replace only macro in first position
					$macro = preg_replace('/{({HOSTNAME'.$i.'}|{HOST\.HOST'.$i.'}):(.*)}/U', '{'.$host['host'].':$2}', $macro);

					// only one simple macro possible inside functional macro
					if ($macro !== $macroTmp) {
						break;
					}
				}
			}

			// try to create valid expression
			$expressionData = new CTriggerExpression();

			if (!$expressionData->parse($macro) || !isset($expressionData->expressions[0])) {
				continue;
			}

			// look in DB for corresponding item
			$itemHost = $expressionData->expressions[0]['host'];
			$key = $expressionData->expressions[0]['item'];
			$function = $expressionData->expressions[0]['functionName'];

			$item = API::Item()->get([
				'output' => ['itemid', 'value_type', 'units', 'valuemapid', 'lastvalue', 'lastclock'],
				'webitems' => true,
				'filter' => [
					'host' => $itemHost,
					'key_' => $key
				]
			]);

			$item = reset($item);

			// if no corresponding item found with functional macro key and host
			if (!$item) {
				$label = str_replace($expr, UNRESOLVED_MACRO_STRING, $label);

				continue;
			}

			// do function type (last, min, max, avg) related actions
			if ($function === 'last') {
				$value = $item['lastclock'] ? formatHistoryValue($item['lastvalue'], $item) : UNRESOLVED_MACRO_STRING;
			}
			else {
				$value = getItemFunctionalValue($item, $function, $expressionData->expressions[0]['functionParamList'][0]);
			}

			if (isset($value)) {
				$label = str_replace($expr, $value, $label);
			}
		}

		return $label;
	}

	/**
	 * Resolve all kinds of macros in map labels.
	 *
	 * @param array  $selement
	 * @param string $selement['label']						label to expand
	 * @param int    $selement['elementtype']				element type
	 * @param int    $selement['elementid']					element id
	 * @param string $selement['elementExpressionTrigger']	if type is trigger, then trigger expression
	 *
	 * @return string
	 */
	public function resolveMapLabelMacrosAll(array $selement) {
		$label = $selement['label'];

		// for host and trigger items expand macros if they exists
		if (($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST || $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
				&& (strpos($label, 'HOST.NAME') !== false
						|| strpos($label, 'HOSTNAME') !== false /* deprecated */
						|| strpos($label, 'HOST.HOST') !== false
						|| strpos($label, 'HOST.DESCRIPTION') !== false
						|| strpos($label, 'HOST.DNS') !== false
						|| strpos($label, 'HOST.IP') !== false
						|| strpos($label, 'IPADDRESS') !== false /* deprecated */
						|| strpos($label, 'HOST.CONN') !== false)) {
			// priorities of interface types doesn't match interface type ids in DB
			$priorities = [
				INTERFACE_TYPE_AGENT => 4,
				INTERFACE_TYPE_SNMP => 3,
				INTERFACE_TYPE_JMX => 2,
				INTERFACE_TYPE_IPMI => 1
			];

			// get host data if element is host
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
				$res = DBselect(
					'SELECT hi.ip,hi.dns,hi.useip,h.host,h.name,h.description,hi.type AS interfacetype'.
					' FROM interface hi,hosts h'.
					' WHERE hi.hostid=h.hostid'.
						' AND hi.main=1 AND hi.hostid='.zbx_dbstr($selement['elementid'])
				);

				// process interface priorities
				$tmpPriority = 0;

				while ($dbHost = DBfetch($res)) {
					if ($priorities[$dbHost['interfacetype']] > $tmpPriority) {
						$resHost = $dbHost;
						$tmpPriority = $priorities[$dbHost['interfacetype']];
					}
				}

				$hostsByNr[''] = $resHost;
			}
			// get trigger host list if element is trigger
			else {
				$res = DBselect(
					'SELECT hi.ip,hi.dns,hi.useip,h.host,h.name,h.description,f.functionid,hi.type AS interfacetype'.
					' FROM interface hi,items i,functions f,hosts h'.
					' WHERE h.hostid=hi.hostid'.
						' AND hi.hostid=i.hostid'.
						' AND i.itemid=f.itemid'.
						' AND hi.main=1 AND f.triggerid='.zbx_dbstr($selement['elementid']).
					' ORDER BY f.functionid'
				);

				// process interface priorities, build $hostsByFunctionId array
				$tmpFunctionId = -1;

				while ($dbHost = DBfetch($res)) {
					if ($dbHost['functionid'] != $tmpFunctionId) {
						$tmpPriority = 0;
						$tmpFunctionId = $dbHost['functionid'];
					}

					if ($priorities[$dbHost['interfacetype']] > $tmpPriority) {
						$hostsByFunctionId[$dbHost['functionid']] = $dbHost;
						$tmpPriority = $priorities[$dbHost['interfacetype']];
					}
				}

				// get all function ids from expression and link host data against position in expression
				preg_match_all('/\{([0-9]+)\}/', $selement['elementExpressionTrigger'], $matches);

				$hostsByNr = [];

				foreach ($matches[1] as $i => $functionid) {
					if (isset($hostsByFunctionId[$functionid])) {
						$hostsByNr[$i + 1] = $hostsByFunctionId[$functionid];
					}
				}

				// for macro without numeric index
				if (isset($hostsByNr[1])) {
					$hostsByNr[''] = $hostsByNr[1];
				}
			}

			// resolve functional macros like: {{HOST.HOST}:log[{HOST.HOST}.log].last(0)}
			$label = $this->resolveMapLabelMacros($label, $hostsByNr);

			// resolves basic macros
			// $hostsByNr possible keys: '' and 1-9
			foreach ($hostsByNr as $i => $host) {
				$replace = [
					'{HOST.NAME'.$i.'}' => $host['name'],
					'{HOSTNAME'.$i.'}' => $host['host'],
					'{HOST.HOST'.$i.'}' => $host['host'],
					'{HOST.DESCRIPTION'.$i.'}' => $host['description'],
					'{HOST.DNS'.$i.'}' => $host['dns'],
					'{HOST.IP'.$i.'}' => $host['ip'],
					'{IPADDRESS'.$i.'}' => $host['ip'],
					'{HOST.CONN'.$i.'}' => $host['useip'] ? $host['ip'] : $host['dns']
				];

				$label = str_replace(array_keys($replace), $replace, $label);
			}
		}
		else {
			// resolve functional macros like: {sampleHostName:log[{HOST.HOST}.log].last(0)}, if no host provided
			$label = $this->resolveMapLabelMacros($label);
		}

		// resolve map specific processing consuming macros
		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST:
			case SYSMAP_ELEMENT_TYPE_MAP:
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				if (strpos($label, '{TRIGGERS.UNACK}') !== false) {
					$label = str_replace('{TRIGGERS.UNACK}', get_triggers_unacknowledged($selement), $label);
				}
				if (strpos($label, '{TRIGGERS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGERS.PROBLEM.UNACK}', get_triggers_unacknowledged($selement, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.UNACK}', get_events_unacknowledged($selement), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.PROBLEM.UNACK}',
						get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE), $label);
				}
				if (strpos($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}') !== false) {
					$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}',
						get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE), $label);
				}
				if (strpos($label, '{TRIGGERS.ACK}') !== false) {
					$label = str_replace('{TRIGGERS.ACK}',
						get_triggers_unacknowledged($selement, null, true), $label);
				}
				if (strpos($label, '{TRIGGERS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGERS.PROBLEM.ACK}',
						get_triggers_unacknowledged($selement, true, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.ACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.ACK}',
						get_events_unacknowledged($selement, null, null, true), $label);
				}
				if (strpos($label, '{TRIGGER.EVENTS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGER.EVENTS.PROBLEM.ACK}',
						get_events_unacknowledged($selement, null, TRIGGER_VALUE_TRUE, true), $label);
				}
				if (strpos($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}') !== false) {
					$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}',
						get_events_unacknowledged($selement, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE, true), $label);
				}
				break;
		}

		return $label;
	}

	/**
	 * Replace user macros found in string. If there are multiple macros in a string, they are replaced one by one.
	 * This is because once string has changed, other macro postions are now different and they need to be recalculated
	 * after each replace. If macro cannot be replaced with value, try other corresponding macros.
	 * For example:
	 *		$macros[
	 *			{$A} => {$A},
	 *			{$B} => b,
	 *			{$C} => {$C},
	 *			{$D} => d
	 *		];
	 *
	 *		$string = "{$A}{$B}{$C}{$D}";
	 *
	 *	Sequence:
	 *	1) $string = "{$A}{$B}{$C}{$D}";	// try to replace {$A}, fail, move to {$B};
	 *	2) $string = "{$A}b{$C}{$D}";		// try to replace {$B}, succeed, recalculate positions and restart;
	 *	3) $string = "{$A}b{$C}{$D}";		// try to replace {$A}, fail, move to {$C}, fail, move to {$D};
	 *	4) $string = "{$A}b{$C}d";			// try to replace {$D}, succeed, recalculate positions, no more, exit.
	 *
	 * @param string $string	String that contains macros.
	 * @param array $macros		Array of macros and values.
	 *
	 * @return string
	 */
	private function replaceUserMacros($string, array $macros) {
		$user_macros = (new CUserMacroParser($string, false))->getMacros();
		$macro_count = count($user_macros);
		$i = 0;

		if ($user_macros) {
			while ($user_macros || $i == ($macro_count - 1)) {
				if (array_key_exists($user_macros[$i]['macro'], $macros)) {
					if ($macros[$user_macros[$i]['macro']] !== $user_macros[$i]['macro']) {
						// Replace macro to value. Note that character positions are now changed.
						$string = substr_replace($string, $macros[$user_macros[$i]['macro']],
							$user_macros[$i]['positions']['start'],
							$user_macros[$i]['positions']['length']
						);

						// If there we more macros, recheck the string to get remaining macro positions.
						if (($macro_count - 1) > $i) {
							$user_macros = (new CUserMacroParser($string, false))->getMacros();
							$macro_count = count($user_macros);
							$i = 0;
						}
						else {
							break;
						}
					}
					else {
						if (($macro_count - 1) > $i) {
							$i++;
						}
						else {
							// That was the last one and we could not replace it.
							break;
						}
					}
				}
				else {
					break;
				}
			}
		}

		return $string;
	}

	/**
	 * Replace reference macros found in string.
	 *
	 * @param string $string	String that contains macros.
	 * @param array $macros		Array of macros and values.
	 *
	 * @return string
	 */
	private function replaceReferenceMacros($string, array $macros) {
		return str_replace(array_keys($macros), array_values($macros), $string);
	}
}
