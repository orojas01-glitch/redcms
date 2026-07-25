<%@ WebHandler Language="C#" Class="Handler" Debug="false" %>

using System.Web;

public class Handler : IHttpHandler {
    public void ProcessRequest(HttpContext context) {
        context.Response.StatusCode = 410;
        context.Response.ContentType = "text/plain";
        context.Response.Write("mail failed\n");
    }

    public bool IsReusable {
        get {
            return false;
        }
    }
}
