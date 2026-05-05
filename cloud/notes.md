# Cloud Patterns — Categoría General

## ¿Qué resuelven los patrones cloud?

Los patrones cloud resuelven problemas específicos de **sistemas distribuidos** — cosas
que no existen en una app monolítica corriendo en un solo proceso.

El hilo común es: **todos asumen que la red falla, los servicios se caen,
y el sistema debe sobrevivir igual.**

## Patrones de esta categoría

| Patrón | Problema que resuelve |
|---|---|
| **Retry** | Fallas transitorias — reintentar operaciones que fallaron momentáneamente |
| **Circuit Breaker** | Cascading failures — dejar de llamar a un servicio caído para no hundir el sistema entero |
| **Bulkhead** | Aislamiento de recursos — que un servicio lento no consuma todos los threads y afecte a los demás |
| **API Gateway** | Punto de entrada único — routing, auth, rate limiting centralizados para múltiples microservicios |
| **Saga** | Transacciones distribuidas — coordinar operaciones que afectan múltiples servicios sin una DB compartida |
| **Sidecar** | Funcionalidad auxiliar — separar concerns como logging, certs o proxy del contenedor principal |
| **Ambassador** | Proxy saliente — centralizar lógica de red (retry, timeout, auth) para llamadas a servicios externos |
| **Strangler Fig** | Migración incremental — reemplazar un monolito pieza a pieza sin reescribir todo de una vez |

## Relaciones entre patrones

**Retry + Circuit Breaker** son complementarios y casi siempre van juntos en producción:
- Retry dice "inténtalo de nuevo"
- Circuit Breaker dice "ya basta, espera a que el servicio se recupere"

Sin Circuit Breaker, un Retry agresivo puede empeorar la situación — satura
un servicio ya sobrecargado con más llamadas en el peor momento.

**Bulkhead + Circuit Breaker** también se combinan frecuentemente:
Bulkhead aisla los recursos, Circuit Breaker corta el flujo cuando el servicio falla.

**Ambassador** es esencialmente un Retry + timeout + Circuit Breaker empaquetado
como un proxy independiente, en lugar de implementar esa lógica en cada servicio.
