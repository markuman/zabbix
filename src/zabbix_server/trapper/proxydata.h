/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_PROXYDATA_H
#define ZABBIX_PROXYDATA_H

#include "zbxjson.h"
#include "zbxcachehistory.h"

extern int	CONFIG_TIMEOUT;
extern int	CONFIG_TRAPPER_TIMEOUT;

void	zbx_recv_proxy_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		zbx_events_funcs_t events_cbs);
void	zbx_send_proxy_data(zbx_socket_t *sock, zbx_timespec_t *ts, const zbx_config_comms_args_t *zbx_config);
void	zbx_send_task_data(zbx_socket_t *sock, zbx_timespec_t *ts, const zbx_config_comms_args_t *zbx_config);

int	zbx_send_proxy_data_response(const DC_PROXY *proxy, zbx_socket_t *sock, const char *info, int status,
		int upload_status);

int	init_proxy_history_lock(char **error);
void	free_proxy_history_lock(void);

#endif
