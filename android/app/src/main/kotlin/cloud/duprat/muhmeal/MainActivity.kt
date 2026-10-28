package cloud.duprat.muhmeal

import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Color
import android.os.Bundle
import android.webkit.CookieManager
import android.webkit.RenderProcessGoneDetail
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebResourceResponse
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.FrameLayout
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.core.graphics.Insets
import androidx.core.net.toUri
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.lifecycle.Lifecycle

class MainActivity : ComponentActivity() {
    private lateinit var root: FrameLayout
    private lateinit var webView: WebView
    private val hosts = Hosts(BuildConfig.BASE_URL.toUri().host)
    private var lastHost: String? = null
    private var failed = false

    private val back = object : OnBackPressedCallback(false) {
        override fun handleOnBackPressed() = webView.goBack()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val bars = SystemBarStyle.light(CREAM, CREAM)
        enableEdgeToEdge(bars, bars)
        webView = WebView(this)
        root = FrameLayout(this)
        root.setBackgroundColor(CREAM)
        root.addView(webView)
        setContentView(root)
        ViewCompat.setOnApplyWindowInsetsListener(root) { view, insets ->
            val system = insets.getInsets(SYSTEM)
            view.setPadding(system.left, system.top, system.right, system.bottom)
            WindowInsetsCompat.Builder(insets).setInsets(SYSTEM, Insets.NONE).build()
        }
        onBackPressedDispatcher.addCallback(this, back)
        webView.setBackgroundColor(Color.TRANSPARENT)
        with(webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            userAgentString = "$userAgentString MuhmealApp/${BuildConfig.VERSION_NAME}"
        }
        webView.webViewClient = Client()
        webView.loadUrl(BuildConfig.BASE_URL + "/")
    }

    override fun onResume() {
        super.onResume()
        if (failed) webView.reload()
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    override fun onDestroy() {
        root.removeView(webView)
        webView.destroy()
        super.onDestroy()
    }

    private fun retrySoon(view: WebView) {
        failed = true
        view.postDelayed({ if (failed && lifecycle.currentState.isAtLeast(Lifecycle.State.RESUMED)) view.reload() }, RETRY_DELAY_MS)
    }

    private inner class Client : WebViewClient() {
        override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
            if (!request.isForMainFrame || hosts.opensInApp(request.url.host)) return false
            runCatching { startActivity(Intent(Intent.ACTION_VIEW, request.url)) }
            return true
        }

        override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
            failed = false
        }

        override fun onPageFinished(view: WebView, url: String) {
            val host = url.toUri().host
            if (hosts.returnedFromAccounts(lastHost, host)) {
                view.clearHistory()
                back.isEnabled = view.canGoBack()
            }
            lastHost = host
        }

        override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
            if (request.isForMainFrame) retrySoon(view)
        }

        override fun onReceivedHttpError(view: WebView, request: WebResourceRequest, errorResponse: WebResourceResponse) {
            if (request.isForMainFrame && errorResponse.statusCode >= 500) retrySoon(view)
        }

        override fun doUpdateVisitedHistory(view: WebView, url: String, isReload: Boolean) {
            back.isEnabled = view.canGoBack()
        }

        override fun onRenderProcessGone(view: WebView, detail: RenderProcessGoneDetail): Boolean {
            recreate()
            return true
        }
    }

    private companion object {
        const val CREAM = 0xFFFAF3E3.toInt()
        const val RETRY_DELAY_MS = 5000L
        val SYSTEM = WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.displayCutout() or WindowInsetsCompat.Type.ime()
    }
}
