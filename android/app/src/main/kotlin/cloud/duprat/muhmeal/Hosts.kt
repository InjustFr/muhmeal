package cloud.duprat.muhmeal

class Hosts(private val app: String?) {
    fun opensInApp(host: String?) = host != null && (host == app || host == ACCOUNTS)

    fun returnedFromAccounts(previous: String?, host: String?) = host != null && host == app && previous == ACCOUNTS

    private companion object {
        const val ACCOUNTS = "accounts.mossyleaf.studio"
    }
}
