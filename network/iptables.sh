#!/bin/bash
# Reglas de firewall inter-VLAN del hipervisor Proxmox (cadena FORWARD).
#
# Instalación en el host Proxmox:
#   cp iptables.sh /usr/local/sbin/tfc-iptables.sh
#   chmod +x /usr/local/sbin/tfc-iptables.sh
#   /usr/local/sbin/tfc-iptables.sh
#
# Direccionamiento:
#   VLAN 10  10.0.10.10  Apache + PHP (web)
#   VLAN 20  10.0.20.10  MariaDB
#   VLAN 30  10.0.30.10  Samba4 AD DC (DNS y autenticación)
#   VLAN 40  10.0.40.0/24 Clientes con agente Telegraf

set -e

WEB="10.0.10.10"
DB="10.0.20.10"
AD="10.0.30.10"
CLIENTES="10.0.40.0/24"
WAN_IF="vmbr0"

# --- Limpieza inicial -------------------------------------------------
iptables -F FORWARD
iptables -A FORWARD -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT

# --- Samba4: accesible desde cualquier VLAN ---------------------------
iptables -A FORWARD -d "$AD" -j ACCEPT

# --- Excepciones permitidas -------------------------------------------
# Apache -> MariaDB (consultas del dashboard)
iptables -A FORWARD -s "$WEB" -d "$DB" -p tcp --dport 3306 -j ACCEPT

# Clientes -> MariaDB (Telegraf inyecta métricas)
iptables -A FORWARD -s "$CLIENTES" -d "$DB" -p tcp --dport 3306 -j ACCEPT

# Apache -> clientes por SSH (botones iniciar/reiniciar del dashboard)
iptables -A FORWARD -s "$WEB" -d "$CLIENTES" -p tcp --dport 22 -j ACCEPT

# Clientes -> Apache (acceso al dashboard)
iptables -A FORWARD -s "$CLIENTES" -d "$WEB" -p tcp -m multiport --dports 80,443 -j ACCEPT

# --- Bloqueos de seguridad --------------------------------------------
# Los clientes no pueden acceder al resto de la VLAN de datos
iptables -A FORWARD -s "$CLIENTES" -d 10.0.20.0/24 -j DROP

# Bloqueo inter-VLAN por defecto
iptables -A FORWARD -s 10.0.10.0/24 -d 10.0.0.0/8 -j DROP
iptables -A FORWARD -s 10.0.20.0/24 -d 10.0.0.0/8 -j DROP
iptables -A FORWARD -s "$CLIENTES" -d 10.0.0.0/8 -j DROP

# --- Acceso al dashboard desde la red local (puerto 8080) -------------
# Elimina este bloque si no necesitas entrar al panel desde fuera de Proxmox.
iptables -t nat -C PREROUTING -i "$WAN_IF" -p tcp --dport 8080 -j DNAT --to-destination "$WEB":80 2>/dev/null \
  || iptables -t nat -A PREROUTING -i "$WAN_IF" -p tcp --dport 8080 -j DNAT --to-destination "$WEB":80

echo "Reglas iptables aplicadas."
