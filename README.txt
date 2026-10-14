SISTEMA DE RED Y MONITORIZACIÓN PARA ENTORNOS DE AUTOMOCIÓN DISTRIBUIDOS
Infraestructura virtualizada sobre Proxmox VE que simula la red de una empresa con varios talleres de reparación 
de automóviles: red segmentada por VLANs, firewall con iptables, dominio con Samba4 AD, monitorización con Telegraf
y un panel web en PHP que permite ver el estado de los servicios críticos y levantarlos con un clic.


CONTEXTO:
La empresa es ficticia y actúa como proveedor de servicios TI para talleres sin departamento informático propio.
El objetivo es reducir el tiempo de detección y recuperación ante caídas de servicio.

DEMO EN VIDEO: https://youtu.be/c4FjBR3F_BU

QUÉ HACE
Segmenta la red en 4 VLANs sobre un bridge VLAN-aware de Proxmox, con política deny all entre VLANs y excepciones mínimas.
Centraliza la identidad con un controlador de dominio Samba4 (tfc.local) que también da DNS.
Monitoriza CPU, memoria y estado de servicios (sssd, ssh, cron, ufw, telegraf) con agentes Telegraf cada 5 segundos.
Almacena las métricas en MariaDB.
Muestra y controla los servicios desde un dashboard web: verde si está activo, rojo si falla, con botones para iniciar o reiniciar el servicio en el cliente remoto.

ARQUITECTURA
                    Internet
                         (NAT, vmbr0)
                
                  Proxmox VE    iptables Y VLAN
             
                        vmbr1 (VLAN-aware)
 
 VLAN 10     VLAN 20       VLAN 30       VLAN 40
 Apache+PHP  MariaDB       Samba4 AD     Clientes
 (web)       (datos)       (DNS+dominio) (Telegraf)

VLAN	Subred	IP	Función
10	10.0.10.0/24	10.0.10.10	Servidor web (Apache + PHP)
20	10.0.20.0/24	10.0.20.10	Base de datos (MariaDB)
30	10.0.30.0/24	10.0.30.10	Samba4 AD DC (DNS y autenticación)
40	10.0.40.0/24	Estática	Clientes y agentes Telegraf

FLUJO DE DATOS:
Telegraf (VLAN 40) → MariaDB (VLAN 20) ← Apache/PHP (VLAN 10). El panel ejecuta acciones sobre el cliente por SSH con un
usuario dedicado (telegraf-restart) que solo puede ejecutar un conjunto fijo de comandos systemctl mediante sudoers.

STACK
Capa	            Tecnología
Virtualización	  Proxmox VE sobre VMware (2 NIC: NAT y Host-Only)
Contenedores	    LXC con Debian 12
Red y seguridad  	Linux bridges, VLAN 802.1Q, iptables, NAT
Identidad	        Samba 4.17 como AD DC, Kerberos, realmd + SSSD en clientes Debian
Base de datos	    MariaDB
Monitorización	  Telegraf 1.30 (outputs.sql, inputs.cpu, inputs.mem, inputs.systemd_units)
Panel web	        Apache + PHP


ESTADO DEL PROYECTO

Componente	                                      Estado
Proxmox, bridges y VLANs          	              Implementado
Reglas iptables entre VLANs	                      Implementado
Samba4 AD DC y DNS interno	                      Implementado
Cliente Debian unido al dominio (SSSD)	          Implementado
Telegraf → MariaDB	                              Implementado
Dashboard con control de servicios	              Implementado
Clientes Windows 11, GPO y OUs	                  Diseñado en la memoria
VPN entre sedes	                                  Simulado (todas las sedes están en un único Proxmox)
Copias de seguridad automáticas	                  Diseñado en la memoria
DHCP en Samba4	                                  Mejora futura (actualmente IP estática)


DOCUMENTACIÓN
Documento	                           Contenido
docs/memoria.pdf	                   Memoria completa: análisis, DAFO, planificación, diseño, presupuesto y mejoras
docs/manual-administrador.pdf	       Instalación y configuración paso a paso
docs/manual-usuario.pdf	             Uso del dashboard

DESPLIEGUE

Guía completa en el manual de administrador. Orden de creación de contenedores:

Samba4 (VLAN 30): DNS externo temporal 8.8.8.8 para descargar paquetes, después se provisiona el dominio y pasa a usar su propio DNS.
MariaDB (VLAN 20): base de datos y usuario telegraf.
Apache + PHP (VLAN 10): panel web.
Cliente (VLAN 40): SSSD, Telegraf y usuario telegraf-restart.

samba-tool domain provision --use-rfc2307 --realm=TFC.LOCAL --domain=TFC \
  --server-role=dc --dns-backend=SAMBA_INTERNAL --adminpass="<CONTRASEÑA>"

Las credenciales del repositorio son placeholders (<CONTRASEÑA>, CAMBIAR_PASSWORD). Cámbialas antes de usar nada de esto.

LIMITACIONES CONOCIDAS

Es un prototipo de laboratorio, no una solución lista para producción:

El panel no tiene autenticación ni HTTPS. La memoria describe login, roles y TLS como requisitos de diseño, pero la versión del repositorio no los implementa. Cualquiera con acceso a la VLAN podría ejecutar acciones sobre los servicios.
Permisos de base de datos demasiado amplios. El usuario telegraf tiene ALL PRIVILEGES sobre su base de datos y MariaDB escucha en 0.0.0.0. Lo correcto es INSERT/SELECT solo donde hagan falta y bind-address en la IP de la VLAN.
Refresco con <meta refresh>, no con AJAX como se plantea en el diseño.
Punto único de fallo: todo corre en un solo nodo de Proxmox.
El firewall perimetral dedicado (OPNsense) no se pudo desplegar por problemas de virtualización anidada, así que la seguridad recae en iptables en el hipervisor.
Contenedores LXC privilegiados, necesarios para Samba4 en este entorno pero con menor aislamiento.


MEJORAS PROPUESTAS
OPNsense como firewall dedicado, con interfaz web, IDS/IPS (Suricata) y VPN (WireGuard).
Clúster Proxmox con alta disponibilidad y almacenamiento compartido.
DHCP integrado en Samba4 con reservas por MAC para servidores.
Soporte remoto con estación de administración gráfica y RDP/xrdp a los clientes.
Autenticación, roles y auditoría de acciones en el panel web.
Escalabilidad a otros sectores con redes de sedes distribuidas (hostelería, retail, franquicias).



USO DE IA Y FUENTES
Reglas iptables: apoyo de IA (Claude) para conectarlas correctamente.
CSS del panel: generado con Gemini.
Lógica PHP: desarrollada por el autor por prueba y error, a partir de ejemplos de la documentación de Telegraf.
Referencias: documentación de Proxmox, wiki de Samba, netfilter/iptables, plugin outputs.sql de Telegraf y systemctl.


AUTOR

David Ruso Quintáns · Técnico Superior en Administración de Sistemas Informáticos en Red

