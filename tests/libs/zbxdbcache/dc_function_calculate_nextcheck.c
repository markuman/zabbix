/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxtrends.h"
#include "zbxlog.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed);

/* copiedf from zbxdbcache/dbconfig.c */
#define ZBX_TRIGGER_TIMER_TRIGGER		0x0001
#define ZBX_TRIGGER_TIMER_FUNCTION_TIME		0x0002
#define ZBX_TRIGGER_TIMER_FUNCTION_TREND	0x0004

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	ZBX_UNUSED(local_server_num);
	ZBX_UNUSED(local_process_type);
	ZBX_UNUSED(local_process_num);

	return 0;
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	ZBX_UNUSED(flags);

	return 0;
}

static int	str_to_timer_type(const char *str)
{
	if (0 == strcmp(str, "ZBX_TRIGGER_TIMER_TRIGGER"))
		return ZBX_TRIGGER_TIMER_TRIGGER;
	if (0 == strcmp(str, "ZBX_TRIGGER_TIMER_FUNCTION_TIME"))
		return ZBX_TRIGGER_TIMER_FUNCTION_TIME;
	if (0 == strcmp(str, "ZBX_TRIGGER_TIMER_FUNCTION_TREND"))
		return ZBX_TRIGGER_TIMER_FUNCTION_TREND;

	fail_msg("unknown timer type \"%s\"", str);

	return ZBX_FUNCTION_TYPE_UNKNOWN;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_timespec_t		ts_from, ts_returned, ts_expected;
	zbx_trigger_timer_t	timer = {0};

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.time"), &ts_from))
		fail_msg("Invalid input time format");

	timer.type = str_to_timer_type(zbx_mock_get_parameter_string("in.type"));
	timer.parameter = zbx_mock_get_parameter_string("in.params");
	timer.lastcheck = ts_from.sec;

	ts_returned.ns = 0;
	ts_returned.sec = zbx_dc_function_calculate_nextcheck(&timer, ts_from.sec, 0);

	if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.nextcheck"), &ts_expected))
		fail_msg("Invalid nextcheck time format");

	zbx_mock_assert_timespec_eq("nextcheck", &ts_expected, &ts_returned);
}
